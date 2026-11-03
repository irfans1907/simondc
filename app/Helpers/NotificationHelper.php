<?php

namespace App\Helpers;

use App\Models\User;

class NotificationHelper
{
    /**
     * Check if a user has notifications enabled for a given type.
     */
    public static function shouldNotify(User $user, string $type): bool
    {
        $preference = $user->notificationPreferences()->where('type', $type)->first();

        // Default to enabled if no preference exists
        return $preference?->enabled ?? true;
    }
}
