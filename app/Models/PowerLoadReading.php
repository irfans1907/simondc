<?php

namespace App\Models;

use Database\Factories\PowerLoadReadingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PowerLoadReading extends Model
{
    /** @use HasFactory<PowerLoadReadingFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'power_load_row_id',
        'period',
        'ampere',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ampere' => 'decimal:2',
    ];

    /**
     * The load row this reading belongs to.
     *
     * @return BelongsTo<PowerLoadRow, $this>
     */
    public function row(): BelongsTo
    {
        return $this->belongsTo(PowerLoadRow::class, 'power_load_row_id');
    }

    /**
     * The user who recorded this reading.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
