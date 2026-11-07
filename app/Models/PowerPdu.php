<?php

namespace App\Models;

use Database\Factories\PowerPduFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PowerPdu extends Model
{
    /** @use HasFactory<PowerPduFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'floor',
        'code',
        'name',
        'sort_order',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'floor' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * The load rows recorded under this PDU.
     *
     * @return HasMany<PowerLoadRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(PowerLoadRow::class)->orderBy('sort_order');
    }
}
