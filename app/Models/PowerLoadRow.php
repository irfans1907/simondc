<?php

namespace App\Models;

use Database\Factories\PowerLoadRowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PowerLoadRow extends Model
{
    /** @use HasFactory<PowerLoadRowFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'power_pdu_id',
        'customer',
        'rack',
        'rack_type',
        'mcb',
        'capacity_amp',
        'phase',
        'service',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'capacity_amp' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    /**
     * The PDU this row belongs to.
     *
     * @return BelongsTo<PowerPdu, $this>
     */
    public function pdu(): BelongsTo
    {
        return $this->belongsTo(PowerPdu::class, 'power_pdu_id');
    }

    /**
     * The monthly ampere readings for this row.
     *
     * @return HasMany<PowerLoadReading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(PowerLoadReading::class);
    }
}
