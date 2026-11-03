<?php

namespace App\Http\Controllers;

use App\Events\FilesChanged;
use App\Helpers\NotificationHelper;
use App\Models\File;
use App\Models\FileShare;
use App\Models\User;
use App\Notifications\FileDownloadedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class SharedWithMeController extends Controller
{
    /**
     * Show files shared with the current user.
     */
    public function index(Request $request): Response
    {
        $folder = $this->resolveFolder($request);
        $userId = $request->user()->id;

        $sharedFileIds = FileShare::where('shared_with', $userId)
            ->pluck('file_id')
            ->toArray();

        if ($folder) {
            $files = File::query()
                ->where('parent_id', $folder->id)
                ->with('owner:id,name')
                ->withCount('shares')
                ->with(['shares' => function ($query) {
                    $query->with('sharedWith:id,name');
                }])
                ->orderByRaw("CASE type WHEN 'folder' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get()
                ->map(function ($file) use ($userId) {
                    $file->shared_with = $file->shares->map(fn ($share) => [
                        'id' => $share->sharedWith->id,
                        'name' => $share->sharedWith->name,
                        'share_id' => $share->id,
                        'permission' => $share->permission,
                    ])->values();
                    unset($file->shares);
                    $file->can_delete = $this->canUserDelete($file, $userId);

                    return $file;
                });
        } else {
            $files = File::query()
                ->whereIn('id', $sharedFileIds)
                ->with('owner:id,name')
                ->withCount('shares')
                ->with(['shares' => function ($query) {
                    $query->with('sharedWith:id,name');
                }])
                ->orderByRaw("CASE type WHEN 'folder' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get()
                ->map(function ($file) use ($userId) {
                    $file->shared_with = $file->shares->map(fn ($share) => [
                        'id' => $share->sharedWith->id,
                        'name' => $share->sharedWith->name,
                        'share_id' => $share->id,
                        'permission' => $share->permission,
                    ])->values();
                    unset($file->shares);
                    $file->can_delete = $this->canUserDelete($file, $userId);

                    return $file;
                });
        }

        $breadcrumbs = $this->breadcrumbs($folder);

        $currentPermission = null;
        if ($folder) {
            $share = FileShare::where('file_id', $folder->id)
                ->where('shared_with', $userId)
                ->first();
            $currentPermission = $share?->permission ?? 'read';
        }

        $rootFolder = $folder ? $this->getRootSharedFolder($folder) : null;

        return Inertia::render('shared-with-me/index', [
            'files' => $files,
            'folder' => $folder ? [
                'id' => $folder->id,
                'name' => $folder->name,
                'user_id' => $folder->user_id,
            ] : null,
            'breadcrumbs' => $breadcrumbs,
            'currentPermission' => $currentPermission,
            'sharedFolderTree' => $rootFolder ? $this->getFolderTree($rootFolder, $folder->id) : [],
            'myFolderTree' => $this->getUserFolderTree($userId),
            'canManage' => $request->user()->hasPermissionTo('manage shared with me'),
        ]);
    }

    /**
     * Upload files to a shared folder.
     */
    public function store(Request $request): RedirectResponse
    {
        $parentId = $request->integer('parent_id');
        $userId = $request->user()->id;

        abort_unless($parentId > 0, 400);

        $share = FileShare::where('file_id', $parentId)
            ->where('shared_with', $userId)
            ->where('permission', 'write')
            ->first();

        abort_unless($share, 403);

        $parent = File::where('id', $parentId)->where('type', 'folder')->firstOrFail();
        $relativePaths = $request->input('relative_paths');

        if ($relativePaths) {
            $folderCache = [];
            $rootFolderName = null;

            $firstRelativePath = $relativePaths[0] ?? '';
            $firstPathParts = explode('/', $firstRelativePath);
            if (count($firstPathParts) > 1) {
                $rootFolderName = $firstPathParts[0];
            }

            if ($rootFolderName) {
                $exists = File::where('user_id', $userId)
                    ->where('parent_id', $parent->id)
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
                array_pop($pathParts);

                $currentParentId = $parent->id;

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
                            'uploaded_by' => $userId,
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
                    'uploaded_by' => $userId,
                    'parent_id' => $currentParentId,
                    'type' => 'file',
                    'name' => $uploadedFile->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $uploadedFile->getMimeType(),
                    'size' => $uploadedFile->getSize(),
                ]);
            }

            activity()->enableLogging();

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder uploaded.')]);
        } else {
            foreach ($request->file('files') as $uploadedFile) {
                $path = $uploadedFile->store('files/'.$userId);

                File::create([
                    'user_id' => $userId,
                    'uploaded_by' => $userId,
                    'parent_id' => $parent->id,
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
     * Create a new folder inside a shared folder.
     */
    public function storeFolder(Request $request): RedirectResponse
    {
        $parentId = $request->integer('parent_id');
        $userId = $request->user()->id;

        abort_unless($parentId > 0, 400);

        $share = FileShare::where('file_id', $parentId)
            ->where('shared_with', $userId)
            ->where('permission', 'write')
            ->first();

        abort_unless($share, 403);

        $parent = File::where('id', $parentId)->where('type', 'folder')->firstOrFail();

        File::create([
            'user_id' => $userId,
            'uploaded_by' => $userId,
            'parent_id' => $parent->id,
            'type' => 'folder',
            'name' => $request->string('name')->toString(),
        ]);

        FilesChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Folder created.')]);

        return $this->backToFolder($parent);
    }

    /**
     * Rename a file or folder in a shared folder.
     */
    public function rename(Request $request, File $file): RedirectResponse
    {
        $userId = $request->user()->id;

        abort_unless($this->canUserWrite($file, $userId) || $file->user_id === $userId, 403);

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

        return back();
    }

    /**
     * Copy a file from a shared folder to the user's own My Files.
     */
    public function copyToMyFiles(Request $request, File $file): RedirectResponse
    {
        $userId = $request->user()->id;

        abort_unless($this->canUserWrite($file, $userId) || $file->user_id === $userId, 403);

        $targetFolderId = $request->input('folder_id');

        if ($targetFolderId) {
            $targetFolder = File::where('id', $targetFolderId)
                ->where('user_id', $userId)
                ->where('type', 'folder')
                ->firstOrFail();
        } else {
            $targetFolder = null;
        }

        if ($file->type === 'file' && $file->path) {
            $newPath = $file->path;
            if (Storage::disk('local')->exists($file->path)) {
                $extension = pathinfo($file->name, PATHINFO_EXTENSION);
                $newPath = 'files/'.$userId.'/'.Str::uuid().($extension ? '.'.$extension : '');
                Storage::disk('local')->copy($file->path, $newPath);
            }

            File::create([
                'user_id' => $userId,
                'uploaded_by' => $userId,
                'parent_id' => $targetFolder?->id,
                'type' => 'file',
                'name' => $file->name,
                'path' => $newPath,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
            ]);
        } else {
            File::create([
                'user_id' => $userId,
                'uploaded_by' => $userId,
                'parent_id' => $targetFolder?->id,
                'type' => 'folder',
                'name' => $file->name,
            ]);
        }

        FilesChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('File copied to My Files.')]);

        return back();
    }

    /**
     * Delete a file from a shared folder.
     */
    public function destroy(File $file, Request $request): RedirectResponse
    {
        $userId = $request->user()->id;

        abort_unless($this->canUserDelete($file, $userId), 403);

        $parent = $file->parent;

        $this->deleteFromDisk($file);
        $file->delete();

        FilesChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deleted.')]);

        if ($parent) {
            return to_route('shared-with-me.index', ['folder' => $parent->id]);
        }

        return to_route('shared-with-me.index');
    }

    /**
     * Download a shared file.
     */
    public function download(File $file, Request $request): StreamedResponse|RedirectResponse
    {
        $this->ensureSharedWith($file, $request->user());

        if ($file->owner && $file->owner->id !== $request->user()->id) {
            if (NotificationHelper::shouldNotify($file->owner, 'file_downloaded')) {
                $file->owner->notify(new FileDownloadedNotification($file, $request->user()));
            }
        }

        if ($file->type === 'folder') {
            return $this->downloadFolder($file, $request);
        }

        return Storage::download($file->path, $file->name);
    }

    /**
     * Stream a shared file for inline preview.
     */
    public function preview(File $file, Request $request): StreamedResponse
    {
        $this->ensureSharedWith($file, $request->user());

        $path = $file->path;

        return Storage::response($path, $file->name, [
            'Content-Type' => $file->mime_type,
        ]);
    }

    /**
     * Bulk download shared files as a zip.
     */
    public function bulkDownload(Request $request): StreamedResponse|RedirectResponse
    {
        $ids = $request->input('ids', []);
        $userId = $request->user()->id;

        $sharedFileIds = FileShare::where('shared_with', $userId)
            ->pluck('file_id')
            ->toArray();

        $files = File::query()
            ->whereIn('id', $ids)
            ->whereIn('id', $sharedFileIds)
            ->get();

        if ($files->isEmpty()) {
            return back()->withErrors(['ids' => 'No shared files selected.']);
        }

        $zipFile = tempnam(sys_get_temp_dir(), 'shared-files-').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $file) {
            if ($file->type === 'file' && $file->path) {
                $zip->addFile(Storage::path($file->path), $file->name);
            }
        }

        $zip->close();

        return response()->streamDownload(function () use ($zipFile) {
            readfile($zipFile);
            @unlink($zipFile);
        }, 'shared-files.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * Check if the user has write permission on the shared folder containing the file.
     */
    private function canUserWrite(File $file, int $userId): bool
    {
        $rootFolder = $this->getRootSharedFolder($file);

        if (! $rootFolder) {
            return false;
        }

        return FileShare::where('file_id', $rootFolder->id)
            ->where('shared_with', $userId)
            ->where('permission', 'write')
            ->exists();
    }

    /**
     * Check whether the user can delete a given file.
     */
    private function canUserDelete(File $file, int $userId): bool
    {
        if ($file->user_id === $userId) {
            return true;
        }

        $rootFolder = $this->getRootSharedFolder($file);

        if (! $rootFolder) {
            return false;
        }

        $share = FileShare::where('file_id', $rootFolder->id)
            ->where('shared_with', $userId)
            ->first();

        if (! $share) {
            return false;
        }

        if ($share->permission === 'write') {
            return true;
        }

        return false;
    }

    /**
     * Find the root shared folder that contains this file.
     */
    private function getRootSharedFolder(File $file): ?File
    {
        $current = $file;

        while ($current->parent_id !== null) {
            $current = $current->parent;
        }

        $isShared = FileShare::where('file_id', $current->id)->exists();

        return $isShared ? $current : null;
    }

    /**
     * Get user IDs that have uploaded files in the shared folder tree.
     */
    private function getUserIdsInSharedFolder(File $folder, int $currentUserId): array
    {
        return File::where('parent_id', $folder->id)
            ->where('type', 'file')
            ->where('uploaded_by', '!=', $currentUserId)
            ->pluck('uploaded_by')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Ensure the file is shared with the user, otherwise abort 403.
     */
    private function ensureSharedWith(File $file, $user): void
    {
        $isShared = FileShare::where('file_id', $file->id)
            ->where('shared_with', $user->id)
            ->exists();

        abort_unless($isShared, 403);
    }

    /**
     * Build a folder tree for navigation.
     */
    private function getFolderTree(File $folder, ?int $currentFolderId = null): array
    {
        $children = File::where('parent_id', $folder->id)
            ->where('type', 'folder')
            ->orderBy('name')
            ->get();

        $tree = [];

        foreach ($children as $child) {
            $node = [
                'id' => $child->id,
                'name' => $child->name,
                'expanded' => $child->id === $currentFolderId,
                'children' => [],
            ];

            if ($child->id === $currentFolderId || $this->isDescendantOf($child, $currentFolderId)) {
                $node['children'] = $this->getFolderTree($child, $currentFolderId);
            }

            $tree[] = $node;
        }

        return $tree;
    }

    /**
     * Get the user's own folder tree (My Files).
     */
    private function getUserFolderTree(int $userId, ?int $currentParentId = null): array
    {
        $children = File::where('user_id', $userId)
            ->whereNull('parent_id')
            ->where('type', 'folder')
            ->orderBy('name')
            ->get();

        return $this->buildUserFolderTree($children, $currentParentId);
    }

    /**
     * Recursively build the user's folder tree.
     */
    private function buildUserFolderTree($folders, ?int $currentParentId = null): array
    {
        $tree = [];

        foreach ($folders as $folder) {
            $node = [
                'id' => $folder->id,
                'name' => $folder->name,
                'children' => $this->buildUserFolderTree(
                    File::where('parent_id', $folder->id)->where('type', 'folder')->orderBy('name')->get(),
                    $currentParentId,
                ),
            ];

            $tree[] = $node;
        }

        return $tree;
    }

    /**
     * Check if a file is a descendant of a given folder ID.
     */
    private function isDescendantOf(File $file, ?int $ancestorId): bool
    {
        if ($ancestorId === null) {
            return false;
        }

        $current = $file;

        while ($current->parent_id !== null) {
            if ($current->parent_id === $ancestorId) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }

    /**
     * Download a folder and its contents as a zip.
     */
    private function downloadFolder(File $folder, Request $request): StreamedResponse
    {
        $zipFile = tempnam(sys_get_temp_dir(), 'shared-folder-').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $this->addFolderToZip($folder, $zip, '');

        $zip->close();

        return response()->streamDownload(function () use ($zipFile) {
            readfile($zipFile);
            @unlink($zipFile);
        }, $folder->name.'.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * Recursively add folder contents to zip.
     */
    private function addFolderToZip(File $folder, ZipArchive $zip, string $prefix): void
    {
        $children = File::where('parent_id', $folder->id)->get();

        foreach ($children as $child) {
            $path = $prefix.$child->name;

            if ($child->type === 'folder') {
                $zip->addEmptyDir($path.'/');
                $this->addFolderToZip($child, $zip, $path.'/');
            } elseif ($child->path) {
                $zip->addFile(Storage::path($child->path), $path);
            }
        }
    }

    /**
     * Resolve the folder from the request.
     */
    private function resolveFolder(Request $request): ?File
    {
        $folderId = $request->input('folder');

        if ($folderId === null) {
            return null;
        }

        return File::where('id', $folderId)
            ->where('type', 'folder')
            ->firstOrFail();
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
     * Redirect back to the folder the user was viewing.
     */
    private function backToFolder(?File $parent): RedirectResponse
    {
        if ($parent === null) {
            return to_route('shared-with-me.index');
        }

        return to_route('shared-with-me.index', ['folder' => $parent->id]);
    }
}
