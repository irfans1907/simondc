<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingController extends Controller
{
    /**
     * Show notification settings.
     */
    public function edit(Request $request): Response
    {
        $preferences = $request->user()
            ->notificationPreferences()
            ->pluck('enabled', 'type')
            ->toArray();

        $types = [
            'file_shared' => [
                'label' => 'File shared with me',
                'description' => 'When someone shares a file with you',
            ],
            'file_downloaded' => [
                'label' => 'File downloaded',
                'description' => 'When someone downloads a file you shared',
            ],
            'file_share_removed' => [
                'label' => 'Share removed',
                'description' => 'When someone removes your access to a file',
            ],
            'system' => [
                'label' => 'System announcements',
                'description' => 'Important updates and maintenance notices',
            ],
        ];

        return Inertia::render('settings/notifications', [
            'preferences' => $preferences,
            'types' => $types,
        ]);
    }

    /**
     * Update notification preferences.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preferences' => 'required|array',
            'preferences.*' => 'boolean',
        ]);

        foreach ($validated['preferences'] as $type => $enabled) {
            NotificationPreference::updateOrCreate(
                ['user_id' => $request->user()->id, 'type' => $type],
                ['enabled' => $enabled],
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification preferences updated.')]);

        return to_route('notification-settings.edit');
    }
}
