<?php

namespace App\Notifications;

use App\Models\File;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FileSharedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public File $file,
        public User $sharedBy,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'file_shared',
            'message' => $this->file->type === 'folder'
                ? "{$this->sharedBy->name} shared a folder \"{$this->file->name}\" with you"
                : "{$this->sharedBy->name} shared a file \"{$this->file->name}\" with you",
            'file_id' => $this->file->id,
            'file_name' => $this->file->name,
            'shared_by_id' => $this->sharedBy->id,
            'shared_by_name' => $this->sharedBy->name,
        ];
    }
}
