<?php

namespace App\Http\Controllers;

use App\Events\FilesChanged;
use App\Helpers\NotificationHelper;
use App\Models\File;
use App\Models\FileShare;
use App\Models\User;
use App\Notifications\FileSharedNotification;
use App\Notifications\FileShareRemovedNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FileShareController extends Controller
{
    /**
     * Share a file with one or more users.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'file_id' => 'required|exists:files,id',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'permission' => 'nullable|string|in:read,write',
        ]);

        $file = File::findOrFail($validated['file_id']);

        abort_unless($file->user_id === $request->user()->id, 403);

        $permission = $validated['permission'] ?? 'read';
        $shared = 0;
        $skipped = 0;

        foreach ($validated['user_ids'] as $userId) {
            if ($userId == $request->user()->id) {
                $skipped++;

                continue;
            }

            FileShare::updateOrCreate(
                [
                    'file_id' => $file->id,
                    'shared_with' => $userId,
                ],
                [
                    'shared_by' => $request->user()->id,
                    'permission' => $permission,
                ],
            );

            $recipient = User::find($userId);
            if ($recipient && NotificationHelper::shouldNotify($recipient, 'file_shared')) {
                $recipient->notify(new FileSharedNotification($file, $request->user()));
            }

            $shared++;
        }

        FilesChanged::dispatch();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __("File shared with {$shared} user(s)."),
        ]);

        return back();
    }

    /**
     * Remove a file share.
     */
    public function destroy(FileShare $fileShare, Request $request)
    {
        abort_unless(
            $fileShare->shared_by === $request->user()->id
            || $fileShare->file->user_id === $request->user()->id,
            403,
        );

        $recipient = User::find($fileShare->shared_with);

        if ($recipient && $recipient->id !== $request->user()->id && NotificationHelper::shouldNotify($recipient, 'file_share_removed')) {
            $recipient->notify(new FileShareRemovedNotification($fileShare->file, $request->user()));
        }

        $fileShare->delete();

        FilesChanged::dispatch();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Share removed.'),
        ]);

        return back();
    }

    /**
     * Update the permission level of a file share.
     */
    public function updatePermission(FileShare $fileShare, Request $request)
    {
        abort_unless($fileShare->file->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'permission' => 'required|string|in:read,write',
        ]);

        $fileShare->update(['permission' => $validated['permission']]);

        FilesChanged::dispatch();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Permission updated to :permission.', ['permission' => $validated['permission']]),
        ]);

        return back();
    }
}
