<?php

namespace App\Models;

use Database\Factories\ChecklistItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistItem extends Model
{
    /** @use HasFactory<ChecklistItemFactory> */
    use HasFactory;

    /**
     * Allowed input types for a checklist item.
     *
     * @var list<string>
     */
    public const INPUT_TYPES = ['number', 'status'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'checklist_category_id',
        'asset_id',
        'scope',
        'section',
        'group',
        'label',
        'unit',
        'input_type',
        'min_value',
        'max_value',
        'sort_order',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'min_value' => 'decimal:2',
        'max_value' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * The category this item belongs to.
     *
     * @return BelongsTo<ChecklistCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ChecklistCategory::class, 'checklist_category_id');
    }

    /**
     * The asset this item is linked to, when the item tracks one device.
     *
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * The recorded entries for this item.
     *
     * @return HasMany<ChecklistEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(ChecklistEntry::class);
    }
}
