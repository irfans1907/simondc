<?php

namespace App\Models;

use Database\Factories\ChecklistEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistEntry extends Model
{
    /** @use HasFactory<ChecklistEntryFactory> */
    use HasFactory;

    /**
     * Allowed status values for status-based items.
     *
     * @var list<string>
     */
    public const STATUSES = ['OK', 'NG', 'IDLE'];

    /**
     * Allowed sources for an entry.
     *
     * @var list<string>
     */
    public const SOURCES = ['manual', 'scan'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'checklist_item_id',
        'entry_date',
        'time_slot',
        'value',
        'status',
        'note',
        'source',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'entry_date' => 'date',
        'value' => 'decimal:2',
    ];

    /**
     * The item this entry records.
     *
     * @return BelongsTo<ChecklistItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }

    /**
     * The user who recorded this entry.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
