<?php

namespace App\Models;

use App\Concerns\DescribesActivityChanges;
use Database\Factories\MaintenanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Maintenance extends Model
{
    /** @use HasFactory<MaintenanceFactory> */
    use DescribesActivityChanges, HasFactory, LogsActivity;

    /**
     * Allowed maintenance results.
     *
     * @var list<string>
     */
    public const RESULTS = ['Normal', 'Bermasalah'];

    /**
     * Workflow stages of a maintenance record. Scheduled becomes On
     * Progress automatically once the date has arrived; Done is never
     * set by hand — it always follows a Normal result (see
     * MaintenanceController::statusForResult()).
     *
     * @var list<string>
     */
    public const STATUSES = ['Scheduled', 'On Progress', 'Done'];

    public const STATUS_SCHEDULED = 'Scheduled';

    public const STATUS_IN_PROGRESS = 'On Progress';

    public const STATUS_DONE = 'Done';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'asset_id',
        'maintenance_date',
        'end_date',
        'status',
        'result',
        'evidence_photo',
        'document',
    ];

    /**
     * The asset that was maintained.
     *
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * The trouble ticket created from this maintenance, if any.
     *
     * @return HasOne<Troubleshoot, $this>
     */
    public function troubleshoot(): HasOne
    {
        return $this->hasOne(Troubleshoot::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('maintenance')
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(function (string $eventName): string {
                $assetName = $this->describeAssetName();

                return match ($eventName) {
                    'created' => 'created maintenance for asset "'.$assetName.'" ('.$this->maintenance_date.')',
                    'updated' => $this->describeMaintenanceUpdate($assetName),
                    'deleted' => 'deleted maintenance for asset "'.$assetName.'" ('.$this->maintenance_date.')',
                    default => $eventName,
                };
            });
    }

    /**
     * The related asset's name, looked up fresh (the `asset` relation may
     * be cached from an earlier description), or its id when missing.
     */
    protected function describeAssetName(): string
    {
        if ($this->asset_id === null) {
            return 'no asset';
        }

        return Asset::query()->find($this->asset_id)?->name ?? '#'.$this->asset_id;
    }

    /**
     * Build a description listing each changed attribute and its old/new value.
     */
    protected function describeMaintenanceUpdate(string $assetName): string
    {
        $parts = $this->describeChangedAttributes([
            'asset_id' => fn (mixed $id): string => Asset::query()->find($id)?->name ?? '#'.$id,
        ]);

        if ($parts === []) {
            return 'updated maintenance for asset "'.$assetName.'"';
        }

        return 'updated maintenance for asset "'.$assetName.'": '.implode(', ', $parts);
    }
}
