<?php

namespace App\Models;

use App\Concerns\DescribesActivityChanges;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use DescribesActivityChanges, HasFactory, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'category_id',
        'subcategory_id',
        'brand',
        'type',
        'model',
        'serial_number',
        'capacity',
        'manufacture_year',
        'operation_year',
        'quantity',
        'floor',
        'room',
        'notes',
        'in_checklist',
        'in_maintenance',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'in_checklist' => 'boolean',
        'in_maintenance' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The MCB slots of this asset when it is a PDU panel.
     *
     * @return HasMany<PanelMcb, $this>
     */
    public function panelMcbs(): HasMany
    {
        return $this->hasMany(PanelMcb::class);
    }

    /**
     * The maintenance records of this asset.
     *
     * @return HasMany<Maintenance, $this>
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    /**
     * The trouble tickets of this asset.
     *
     * @return HasMany<Troubleshoot, $this>
     */
    public function troubleshoots(): HasMany
    {
        return $this->hasMany(Troubleshoot::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('asset')
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(function (string $eventName): string {
                return match ($eventName) {
                    'created' => 'created asset "'.$this->name.'" ('.$this->describeAssetCategory().')',
                    'updated' => $this->describeAssetUpdate(),
                    'deleted' => 'deleted asset "'.$this->name.'" ('.$this->describeAssetCategory().')',
                    default => $eventName,
                };
            });
    }

    /**
     * The category label used in the create/delete descriptions. The name
     * is looked up fresh: the `category` relation may already be cached
     * from an earlier description and would then be stale.
     */
    protected function describeAssetCategory(): string
    {
        if ($this->category_id === null) {
            return 'no category';
        }

        $name = Category::query()->find($this->category_id)?->name;

        return 'category "'.($name ?? '#'.$this->category_id).'"';
    }

    /**
     * Build a description listing each changed attribute and its old/new value.
     */
    protected function describeAssetUpdate(): string
    {
        $parts = $this->describeChangedAttributes([
            'category_id' => fn (mixed $id): string => Category::query()->find($id)?->name ?? (string) $id,
        ]);

        if ($parts === []) {
            return 'updated asset "'.$this->name.'"';
        }

        return 'updated asset "'.$this->name.'": '.implode(', ', $parts);
    }
}
