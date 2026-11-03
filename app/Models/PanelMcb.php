<?php

namespace App\Models;

use App\Concerns\DescribesActivityChanges;
use Database\Factories\PanelMcbFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PanelMcb extends Model
{
    /** @use HasFactory<PanelMcbFactory> */
    use DescribesActivityChanges, HasFactory, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'asset_id',
        'mcb_number',
    ];

    /**
     * The panel asset this MCB belongs to.
     *
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('panel_mcb')
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(function (string $eventName): string {
                $assetName = $this->describeAssetName();

                return match ($eventName) {
                    'created' => 'created MCB "'.$this->mcb_number.'" of asset "'.$assetName.'"',
                    'updated' => $this->describeMcbUpdate($assetName),
                    'deleted' => 'deleted MCB "'.$this->mcb_number.'" of asset "'.$assetName.'"',
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
    protected function describeMcbUpdate(string $assetName): string
    {
        $parts = $this->describeChangedAttributes([
            'asset_id' => fn (mixed $id): string => Asset::query()->find($id)?->name ?? '#'.$id,
        ]);

        if ($parts === []) {
            return 'updated MCB "'.$this->mcb_number.'" of asset "'.$assetName.'"';
        }

        return 'updated MCB "'.$this->mcb_number.'" of asset "'.$assetName.'": '.implode(', ', $parts);
    }
}
