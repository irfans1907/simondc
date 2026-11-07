<?php

namespace App\Models;

use Database\Factories\PowerPanelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PowerPanel extends Model
{
    /** @use HasFactory<PowerPanelFactory> */
    use HasFactory;

    /**
     * The panel groups shown in the PUE report, in display order.
     *
     * @var list<string>
     */
    public const GROUPS = ['MDP', 'SDP', 'IO', 'PAC', 'UPS', 'PDU', 'UTILITY'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'panel_group',
        'name',
        'capacity',
        'unit',
        'note',
        'sort_order',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'capacity' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * The monthly readings for this panel.
     *
     * @return HasMany<PowerPanelReading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(PowerPanelReading::class);
    }
}
