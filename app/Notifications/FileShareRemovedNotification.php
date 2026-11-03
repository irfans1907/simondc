<?php

namespace App\Notifications;

use App\Models\File;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FileShareRemovedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public File $file,
        public User $removedBy,
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
            'type' => 'file_share_removed',
            'message' => "{$this->removedBy->name} removed your access to \"{$this->file->name}\"",
            'file_id' => $this->file->id,
            'file_name' => $this->file->name,
            'removed_by_id' => $this->removedBy->id,
            'removed_by_name' => $this->removedBy->name,
        ];
    }
}
