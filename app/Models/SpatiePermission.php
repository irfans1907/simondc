<?php

namespace App\Models;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Permission as BasePermission;

class SpatiePermission extends BasePermission
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name'])
            ->useLogName('permission')
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $eventName): string => match ($eventName) {
                'created' => 'created permission ":subject.name"',
                'updated' => 'updated permission ":subject.name"',
                'deleted' => 'deleted permission ":subject.name"',
                default => $eventName,
            });
    }
}
