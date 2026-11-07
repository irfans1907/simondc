<?php

namespace App\Models;

use Database\Factories\PowerPanelReadingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PowerPanelReading extends Model
{
    /** @use HasFactory<PowerPanelReadingFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'power_panel_id',
        'period',
        'amp_r',
        'amp_s',
        'amp_t',
        'note',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amp_r' => 'decimal:2',
        'amp_s' => 'decimal:2',
        'amp_t' => 'decimal:2',
    ];

    /**
     * The panel this reading belongs to.
     *
     * @return BelongsTo<PowerPanel, $this>
     */
    public function panel(): BelongsTo
    {
        return $this->belongsTo(PowerPanel::class, 'power_panel_id');
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
