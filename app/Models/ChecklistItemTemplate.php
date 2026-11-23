<?php

namespace App\Models;

use Database\Factories\ChecklistItemTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItemTemplate extends Model
{
    /** @use HasFactory<ChecklistItemTemplateFactory> */
    use HasFactory;

    /**
     * Allowed input types, mirroring ChecklistItem.
     *
     * @var list<string>
     */
    public const INPUT_TYPES = ChecklistItem::INPUT_TYPES;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
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
     * The asset category this template belongs to.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
