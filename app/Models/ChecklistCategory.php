<?php

namespace App\Models;

use Database\Factories\ChecklistCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistCategory extends Model
{
    /** @use HasFactory<ChecklistCategoryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
        'time_slots',
        'sort_order',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'time_slots' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Checklist category slugs whose items are defined per category instead
     * of being generated from each asset's template (the FAP panels).
     *
     * @return list<string>
     */
    public static function categoryScopedSlugs(): array
    {
        return collect(config('checklist.categories', []))
            ->filter(fn (array $category): bool => ($category['asset_items'] ?? true) === false)
            ->pluck('slug')
            ->values()
            ->all();
    }

    /**
     * Whether this category owns its checklist items instead of receiving
     * generated items from the assets assigned to it.
     */
    public function usesCategoryItems(): bool
    {
        return in_array($this->slug, self::categoryScopedSlugs(), true);
    }

    /**
     * Category-scoped checklists only make sense once an asset is assigned
     * to them (the FAP panels); otherwise their template items would show up
     * as an empty tab.
     */
    public function requiresAssignedAsset(): bool
    {
        return $this->usesCategoryItems();
    }

    /**
     * The checklist items belonging to this category.
     *
     * @return HasMany<ChecklistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('sort_order');
    }

    /**
     * The daily notes and risk levels recorded for this category.
     *
     * @return HasMany<ChecklistDayNote, $this>
     */
    public function dayNotes(): HasMany
    {
        return $this->hasMany(ChecklistDayNote::class);
    }

    /**
     * The per-slot risk levels recorded for this category.
     *
     * @return HasMany<ChecklistSlotNote, $this>
     */
    public function slotNotes(): HasMany
    {
        return $this->hasMany(ChecklistSlotNote::class);
    }

    /**
     * The asset categories whose assets feed this checklist category.
     *
     * @return BelongsToMany<Category, $this>
     */
    public function assetCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'checklist_category_asset_category',
            'checklist_category_id',
            'category_id',
        )->withTimestamps();
    }
}
