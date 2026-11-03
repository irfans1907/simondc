<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property bool $enabled
 */
class NotificationPreference extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['user_id', 'type', 'enabled'];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    /**
     * Get the user that owns this preference.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('notification_preference')
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $eventName): string => match ($eventName) {
                'created' => 'created notification preference',
                'updated' => 'updated notification preference',
                'deleted' => 'deleted notification preference',
                default => $eventName,
            });
    }
}
