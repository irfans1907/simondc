<?php

namespace App\Http\Controllers;

use App\Events\FilesChanged;
use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\UpdateFileRequest;
use App\Models\File;
use App\Models\FileShare;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class FileController extends Controller
{
    /**
     * Show the current user's files.
     */
    public function index(Request $request): Response
    {
        $folder = $this->resolveFolder($request);

        $query = File::query()
            ->where('parent_id', $folder?->id);

        // When inside a shared folder, show files from all users with access
        if ($folder) {
            $sharedUserIds = FileShare::where('file_id', $folder->id)
                ->pluck('shared_with')
                ->push($folder->user_id)
                ->unique()
                ->toArray();

            $query->whereIn('user_id', $sharedUserIds);
        } else {
            $query->where('user_id', $request->user()->id);
        }

        $files = $query
            ->with('owner:id,name')
            ->withCount('shares')
            ->with(['shares' => function ($query) {
                $query->with('sharedWith:id,name');
            }])
            ->orderByRaw("CASE type WHEN 'folder' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get()
            ->map(function ($file) {
                $file->shared_with = $file->shares->map(fn ($share) => [
                    'id' => $share->sharedWith->id,
                    'name' => $share->sharedWith->name,
                    'share_id' => $share->id,
                    'permission' => $share->permission,
                ])->values();
                unset($file->shares);

                return $file;
            });

        return Inertia::render('files/index', [
            'files' => $files,
            'folder' => $folder ? [
                'id' => $folder->id,
                'name' => $folder->name,
            ] : null,
            'folders' => File::query()
                ->where('user_id', $request->user()->id)
                ->where('type', 'folder')
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id']),
            'sharedFoldersWithWriteAccess' => $this->getSharedFoldersWithWriteAccess($request->user()->id),
            'users' => User::query()
                ->where('id', '!=', $request->user()->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'storage' => $this->storageInfo(),
            'breadcrumbs' => $this->breadcrumbs($folder),
            'canManage' => $request->user()->hasPermissionTo('manage files'),
        ]);
    }

    /**
     * Upload a file into the current folder.
     */
    public function store(StoreFileRequest $request): RedirectResponse
    {
        $parent = $this->resolveParent($request);
        $relativePaths = $request->input('relative_paths');
        $userId = $request->user()->id;

        if ($relativePaths) {
            // Folder upload: create folder structure and store files
            $folderCache = [];
            $rootFolderName = null;

            // Extract root folder name from first relative path
            $firstRelativePath = $relativePaths[0] ?? '';
            $firstPathParts = explode('/', $firstRelativePath);
            if (count($firstPathParts) > 1) {
                $rootFolderName = $firstPathParts[0];
            }

            // Check if folder with same name already exists
            if ($rootFolderName) {
                $exists = File::where('user_id', $userId)
                    ->where('parent_id', $parent?->id)
                    ->where('type', 'folder')
                    ->where('name', $rootFolderName)
                    ->exists();

                if ($exists) {
                    Inertia::flash('toast', [
                        'type' => 'error',
                        'message' => __('A folder named ":name" already exists.', ['name' => $rootFolderName]),
                    ]);

                    return $this->backToFolder($parent);
                }
            }

            activity()->disableLogging();

            foreach ($request->file('files') as $index => $uploadedFile) {
                $relativePath = $relativePaths[$index] ?? $uploadedFile->getClientOriginalName();
                $pathParts = explode('/', $relativePath);
                array_pop($pathParts); // Remove filename

                // Build folder hierarchy
                $currentParentId = $parent?->id;

                foreach ($pathParts as $folderName) {
                    if ($folderName === '') {
                        continue;
                    }

                    $cacheKey = $currentParentId.'_'.$folderName;

                    if (isset($folderCache[$cacheKey])) {
                        $currentParentId = $folderCache[$cacheKey];

                        continue;
                    }

                    $existingFolder = File::where('user_id', $userId)
                        ->where('parent_id', $currentParentId)
                        ->where('type', 'folder')
                        ->where('name', $folderName)
                        ->first();

                    if ($existingFolder) {
                        $currentParentId = $existingFolder->id;
                    } else {
                        $newFolder = File::create([
                            'user_id' => $userId,
                            'parent_id' => $currentParentId,
                            'type' => 'folder',
                            'name' => $folderName,
                        ]);
                        $currentParentId = $newFolder->id;
                    }

                    $folderCache[$cacheKey] = $currentParentId;
                }

                $path = $uploadedFile->store('files/'.$userId);

                File::create([
                    'user_id' => $userId,
                    'parent_id' => $currentParentId,
                    'type' => 'file',
                    'name' => $uploadedFile->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $uploadedFile->getMimeType(),
                    'size' => $uploadedFile->getSize(),
                ]);
            }

            activity()->enableLogging();

            activity('file')
                ->performedOn($request->user())
                ->withProperties(['folder_name' => $rootFolderName])
                ->event('created')
                ->log("uploaded folder \"{$rootFolderName}\"");

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder uploaded.')]);
        } else {
            // Regular file upload
            foreach ($request->file('files') as $uploadedFile) {
                $path = $uploadedFile->store('files/'.$userId);

                File::create([
                    'user_id' => $userId,
                    'parent_id' => $parent?->id,
                    'type' => 'file',
                    'name' => $uploadedFile->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $uploadedFile->getMimeType(),
                    'size' => $uploadedFile->getSize(),
                ]);
            }

            Inertia::flash('toast', ['type' => 'success', 'message' => __('File uploaded.')]);
        }

        FilesChanged::dispatch();

        return $this->backToFolder($parent);
    }

    /**
     * Create a new folder.
     */
    public function storeFolder(StoreFolderRequest $request): RedirectResponse
    {
        $parent = $this->resolveParent($request);

        File::create([
            'user_id' => $request->user()->id,
            'parent_id' => $parent?->id,
            'type' => 'folder',
            'name' => $request->string('name')->toString(),
        ]);

        FilesChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder created.')]);

        return $this->backToFolder($parent);
    }

    /**
     * Move multiple of the user's files or folders into another folder.
     */
    public function move(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer'],
            'folder_id' => ['nullable', 'integer'],
        ]);

        $files = File::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $data['ids'])
            ->get();

        abort_if($files->isEmpty(), 404);

        $target = $this->resolveFolder($request, 'folder_id');

        $excluded = $files
            ->where('type', 'folder')
            ->flatMap(fn (File $folder): array => [$folder->id, ...$this->descendantIds($folder)])
            ->all();

        $moved = 0;

        $files->each(function (File $file) use ($request, $target, $excluded, &$moved): void {
            if ($file->parent_id === $target?->id) {
                return;
            }

            if (in_array($target?->id, $excluded, true)) {
                return;
            }

            $sourceName = $file->parent?->name ?? 'root';
            $destinationName = $target?->name ?? 'root';

            activity()
                ->withoutLogging(function () use ($file, $target): void {
                    $file->update(['parent_id' => $target?->id]);
                });

            activity('file')
                ->causedBy($request->user())
                ->performedOn($file)
                ->event('moved')
                ->log("moved \"{$file->name}\" from \"{$sourceName}\" to \"{$destinationName}\"");

            $moved++;
        });

        FilesChanged::dispatch();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':count items moved.', ['count' => $moved]),
        ]);

        return $this->backToFolder($this->resolveFolder($request));
    }

    /**
     * Rename the given file or folder.
     */
    public function update(UpdateFileRequest $request, File $file): RedirectResponse
    {
        abort_unless($file->user_id === $request->user()->id, 403);

        $oldName = $file->name;
        $newName = $request->string('name')->toString();

        activity()
            ->withoutLogging(function () use ($file, $newName): void {
                $file->update(['name' => $newName]);
            });

        activity('file')
            ->causedBy($request->user())
            ->performedOn($file)
            ->event('renamed')
            ->log("renamed \"{$oldName}\" to \"{$newName}\"");

        FilesChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Renamed.')]);

        return $this->backToFolder($file->parent);
    }

    /**
     * Delete the given file or folder.
     */
    public function destroy(Request $request, File $file): RedirectResponse
    {
        abort_unless(
            $file->user_id === $request->user()->id
            || $file->uploaded_by === $request->user()->id,
            403,
        );

        $parent = $file->parent;

        $this->deleteFromDisk($file);

        $file->delete();

        FilesChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deleted.')]);

        return $this->backToFolder($parent);
    }

    /**
     * Delete multiple of the user's files or folders at once.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer'],
        ]);

        $files = File::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $data['ids'])
            ->get();

        $deleted = $files->count();

        $files->each(function (File $file): void {
            $this->deleteFromDisk($file);
            $file->delete();
        });

        FilesChanged::dispatch();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':count items deleted.', ['count' => $deleted]),
        ]);

        return $this->backToFolder($this->resolveFolder($request));
    }

    /**
     * Download the given file, or a zip archive of a folder and all descendants.
     */
    public function download(Request $request, File $file): StreamedResponse
    {
        abort_unless($file->user_id === $request->user()->id, 403);

        $kind = $file->type === 'folder' ? 'folder' : 'file';

        activity('file')
            ->causedBy($request->user())
            ->performedOn($file)
            ->event('downloaded')
            ->log("downloaded {$kind} \"{$file->name}\"");

        if ($file->type === 'folder') {
            return $this->zipResponse(collect([$file]), $file->name.'.zip');
        }

        return Storage::download($file->path, $file->name);
    }

    /**
     * Download multiple of the user's files or folders as a single zip archive.
     */
    public function bulkDownload(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer'],
        ]);

        $files = File::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $data['ids'])
            ->get();

        abort_if($files->isEmpty(), 404);

        activity('file')
            ->causedBy($request->user())
            ->event('downloaded')
            ->withProperty('count', $files->count())
            ->log("downloaded {$files->count()} items");

        return $this->zipResponse($files, 'files.zip');
    }

    /**
     * Stream the given file inline so it can be previewed.
     */
    public function preview(Request $request, File $file): StreamedResponse
    {
        abort_unless($file->user_id === $request->user()->id, 403);
        abort_if($file->type === 'folder', 404);

        return Storage::response($file->path, $file->name, [
            'Content-Disposition' => 'inline; filename="'.$file->name.'"',
        ]);
    }

    /**
     * Build a zip archive from the given items and stream it as a download.
     *
     * @param  Collection<int, File>  $items
     */
    private function zipResponse(Collection $items, string $zipName): StreamedResponse
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'files').'.zip';

        $zip = new ZipArchive;

        abort_if($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true, 500);

        $this->addToZip($zip, $items);

        if ($zip->numFiles === 0) {
            $zip->addEmptyDir(rtrim($zipName, '.zip'));
        }

        $zip->close();

        return response()->streamDownload(
            function () use ($tempPath): void {
                readfile($tempPath);
                unlink($tempPath);
            },
            $zipName,
            ['Content-Type' => 'application/zip'],
        );
    }

    /**
     * Recursively add files to the zip, preserving the folder structure.
     *
     * @param  Collection<int, File>  $items
     */
    private function addToZip(ZipArchive $zip, Collection $items, string $prefix = ''): void
    {
        $items->each(function (File $file) use ($zip, $prefix): void {
            if ($file->type === 'file') {
                $path = Storage::disk('local')->path($file->path);

                if (is_file($path)) {
                    $zip->addFile($path, $prefix.$file->name);
                }

                return;
            }

            $file->load('children');
            $this->addToZip($zip, $file->children, $prefix.$file->name.'/');
        });
    }

    /**
     * Resolve the folder requested for the index, scoped to the current user.
     */
    private function resolveFolder(Request $request, string $key = 'folder'): ?File
    {
        $folderId = $request->integer($key);

        if ($folderId < 1) {
            return null;
        }

        $folder = File::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'folder')
            ->find($folderId);

        abort_if($folder === null, 404);

        return $folder;
    }

    /**
     * Resolve the parent folder id from the request, scoped to the current user.
     */
    private function resolveParent(Request $request): ?File
    {
        $parentId = $request->integer('parent_id');

        if ($parentId < 1) {
            return null;
        }

        $parent = File::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'folder')
            ->find($parentId);

        abort_if($parent === null, 404);

        return $parent;
    }

    /**
     * Build the breadcrumb trail from the current folder up to the root.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function breadcrumbs(?File $folder): array
    {
        $crumbs = [];

        for ($current = $folder; $current !== null; $current = $current->parent) {
            array_unshift($crumbs, ['id' => $current->id, 'name' => $current->name]);
        }

        return $crumbs;
    }

    /**
     * Delete a file or folder (and all descendants) from the disk.
     */
    private function deleteFromDisk(File $file): void
    {
        if ($file->type === 'file') {
            Storage::delete($file->path);

            return;
        }

        $file->load('children');
        $file->children->each(fn (File $child) => $this->deleteFromDisk($child));
    }

    /**
     * Collect the ids of every descendant folder of the given folder.
     *
     * @return array<int, int>
     */
    private function descendantIds(File $folder): array
    {
        $ids = [];

        $this->childrenIds($folder, $ids);

        return $ids;
    }

    /**
     * Recursively fill the array with the ids of child folders.
     *
     * @param  array<int, int>  $ids
     */
    private function childrenIds(File $folder, array &$ids): void
    {
        $children = File::query()
            ->where('parent_id', $folder->id)
            ->where('type', 'folder')
            ->pluck('id');

        $children->each(function (int $id) use (&$ids): void {
            $ids[] = $id;
            $this->childrenIds(File::query()->findOrFail($id), $ids);
        });
    }

    /**
     * Get the disk usage of the server hosting the application.
     *
     * @return array{total: int, used: int, free: int}
     */
    private function storageInfo(): array
    {
        $total = (int) disk_total_space(base_path());
        $free = (int) disk_free_space(base_path());

        return [
            'total' => $total,
            'used' => max(0, $total - $free),
            'free' => $free,
        ];
    }

    /**
     * Move files from My Files to a shared folder.
     */
    public function moveToShared(Request $request): RedirectResponse
    {
        $userId = $request->user()->id;

        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer'],
            'folder_id' => ['required', 'integer'],
        ]);

        $targetFolder = File::where('id', $data['folder_id'])
            ->where('type', 'folder')
            ->firstOrFail();

        $share = FileShare::where('file_id', $targetFolder->id)
            ->where('shared_with', $userId)
            ->where('permission', 'write')
            ->first();

        abort_unless($share, 403);

        $files = File::query()
            ->where('user_id', $userId)
            ->whereIn('id', $data['ids'])
            ->get();

        abort_if($files->isEmpty(), 404);

        $moved = 0;

        $files->each(function (File $file) use ($targetFolder, &$moved, $request): void {
            if ($file->parent_id === $targetFolder->id) {
                return;
            }

            $sourceName = $file->parent?->name ?? 'root';
            $destinationName = $targetFolder->name;

            activity()
                ->withoutLogging(function () use ($file, $targetFolder): void {
                    $file->update(['parent_id' => $targetFolder->id]);
                });

            activity('file')
                ->causedBy($request->user())
                ->performedOn($file)
                ->event('moved')
                ->log("moved \"{$file->name}\" from \"{$sourceName}\" to \"{$destinationName}\"");

            $moved++;
        });

        FilesChanged::dispatch();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':count items moved to shared folder.', ['count' => $moved]),
        ]);

        return $this->backToFolder($this->resolveFolder($request));
    }

    /**
     * Get shared folders the user has write access to.
     *
     * @return array<int, array{id: int, name: string, parent_id: int|null}>
     */
    private function getSharedFoldersWithWriteAccess(int $userId): array
    {
        $shareIds = FileShare::where('shared_with', $userId)
            ->where('permission', 'write')
            ->pluck('file_id')
            ->toArray();

        return File::whereIn('id', $shareIds)
            ->where('type', 'folder')
            ->get(['id', 'name', 'parent_id'])
            ->toArray();
    }

    /**
     * Redirect back to the folder the user was viewing.
     */
    private function backToFolder(?File $parent): RedirectResponse
    {
        if ($parent === null) {
            return to_route('files.index');
        }

        return to_route('files.index', ['folder' => $parent->id]);
    }
}
