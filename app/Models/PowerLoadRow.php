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
        'rack_id',
        'customer_id',
        'panel_mcb_id',
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
     * The rack this load row belongs to, when linked. Named "linked" so the
     * legacy free-text `rack` column stays readable via the attribute.
     *
     * @return BelongsTo<Rack, $this>
     */
    public function linkedRack(): BelongsTo
    {
        return $this->belongsTo(Rack::class, 'rack_id');
    }

    /**
     * The customer occupying the rack, when linked. Named "linked" so the
     * legacy free-text `customer` column stays readable via the attribute.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function linkedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * The panel MCB feeding this row, when linked.
     *
     * @return BelongsTo<PanelMcb, $this>
     */
    public function panelMcb(): BelongsTo
    {
        return $this->belongsTo(PanelMcb::class);
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
