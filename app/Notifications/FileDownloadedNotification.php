<?php

namespace App\Notifications;

use App\Models\File;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FileDownloadedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public File $file,
        public User $downloadedBy,
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
            'type' => 'file_downloaded',
            'message' => "{$this->downloadedBy->name} downloaded \"{$this->file->name}\"",
            'file_id' => $this->file->id,
            'file_name' => $this->file->name,
            'downloaded_by_id' => $this->downloadedBy->id,
            'downloaded_by_name' => $this->downloadedBy->name,
        ];
    }
}
