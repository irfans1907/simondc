<?php

namespace App\Models;

use Database\Factories\ChecklistSlotNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistSlotNote extends Model
{
    /** @use HasFactory<ChecklistSlotNoteFactory> */
    use HasFactory;

    /**
     * Allowed risk levels for one time slot.
     *
     * @var list<string>
     */
    public const RISK_LEVELS = ChecklistDayNote::RISK_LEVELS;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'checklist_category_id',
        'entry_date',
        'time_slot',
        'risk_level',
        'note',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'entry_date' => 'date',
    ];

    /**
     * The category this slot note belongs to.
     *
     * @return BelongsTo<ChecklistCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ChecklistCategory::class, 'checklist_category_id');
    }

    /**
     * The user who recorded this slot note.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
