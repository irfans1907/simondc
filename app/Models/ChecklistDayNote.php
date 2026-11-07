<?php

namespace App\Models;

use Database\Factories\ChecklistDayNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistDayNote extends Model
{
    /** @use HasFactory<ChecklistDayNoteFactory> */
    use HasFactory;

    /**
     * Allowed risk levels for a checklist day.
     *
     * @var list<string>
     */
    public const RISK_LEVELS = ['LOW', 'MEDIUM', 'HIGH'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'checklist_category_id',
        'entry_date',
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
     * The category this note belongs to.
     *
     * @return BelongsTo<ChecklistCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ChecklistCategory::class, 'checklist_category_id');
    }

    /**
     * The user who recorded this note.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
