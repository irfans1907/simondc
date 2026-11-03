<?php

namespace App\Models;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role as BaseRole;

class SpatieRole extends BaseRole
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name'])
            ->useLogName('role')
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $eventName): string => match ($eventName) {
                'created' => 'created role ":subject.name"',
                'updated' => 'updated role ":subject.name"',
                'deleted' => 'deleted role ":subject.name"',
                default => $eventName,
            });
    }
}
