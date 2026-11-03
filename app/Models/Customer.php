<?php

namespace App\Models;

use App\Concerns\DescribesActivityChanges;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use DescribesActivityChanges, HasFactory, LogsActivity;

    /**
     * City choices (static list until a cities master exists).
     *
     * @var list<string>
     */
    public const CITIES = [
        'Jakarta',
        'Bandung',
        'Surabaya',
        'Semarang',
        'Medan',
        'Makassar',
    ];

    /**
     * neuCentrIX location choices (static list until the locations menu exists).
     *
     * @var list<string>
     */
    public const LOCATIONS = [
        'neuCentrIX Cikarang',
        'neuCentrIX Bandung',
        'neuCentrIX Surabaya',
        'neuCentrIX Medan',
    ];

    /**
     * Business status choices.
     *
     * @var list<string>
     */
    public const BUSINESS_STATUSES = ['Berbayar', 'Dinas'];

    /**
     * Service status choices.
     *
     * @var list<string>
     */
    public const SERVICE_STATUSES = [
        'Masih Berjalan',
        'Berhenti Berlangganan',
        'Suspend',
        'Booking',
    ];

    /**
     * Power source choices.
     *
     * @var list<string>
     */
    public const POWER_SOURCES = ['Single Source', 'Dual Source'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'rack_id',
        'city',
        'location',
        'customer_name',
        'service_type',
        'sales_channel',
        'business_status',
        'service_status',
        'order_number',
        'sid_rack',
        'activation_date',
        'power_source',
        'panel_asset_id',
        'panel_mcb_id',
        'panel_asset_id_2',
        'panel_mcb_id_2',
        'connectivity',
        'ip_address',
        'switch_type',
        'port',
        'vlan',
        'service_vlan',
        'account_manager',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'activation_date' => 'date:Y-m-d',
    ];

    /**
     * The rack assigned to this customer.
     *
     * @return BelongsTo<Rack, $this>
     */
    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    /**
     * The panel asset feeding this customer (source A).
     *
     * @return BelongsTo<Asset, $this>
     */
    public function panelAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'panel_asset_id');
    }

    /**
     * The MCB slot of this customer (source A).
     *
     * @return BelongsTo<PanelMcb, $this>
     */
    public function panelMcb(): BelongsTo
    {
        return $this->belongsTo(PanelMcb::class, 'panel_mcb_id');
    }

    /**
     * The panel asset feeding this customer (source B, Dual Source only).
     *
     * @return BelongsTo<Asset, $this>
     */
    public function panelAsset2(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'panel_asset_id_2');
    }

    /**
     * The MCB slot of this customer (source B, Dual Source only).
     *
     * @return BelongsTo<PanelMcb, $this>
     */
    public function panelMcb2(): BelongsTo
    {
        return $this->belongsTo(PanelMcb::class, 'panel_mcb_id_2');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('customer')
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(function (string $eventName): string {
                return match ($eventName) {
                    'created' => 'created customer "'.$this->customer_name.'"',
                    'updated' => $this->describeCustomerUpdate(),
                    'deleted' => 'deleted customer "'.$this->customer_name.'"',
                    default => $eventName,
                };
            });
    }

    /**
     * Build a description listing each changed attribute and its old/new value.
     */
    protected function describeCustomerUpdate(): string
    {
        $parts = $this->describeChangedAttributes([
            'activation_date' => fn (mixed $value): string => $value ?? 'none',
            'rack_id' => fn (mixed $id): string => Rack::query()->find($id)?->rack_code ?? '#'.$id,
            'panel_asset_id' => fn (mixed $id): string => Asset::query()->find($id)?->name ?? '#'.$id,
            'panel_asset_id_2' => fn (mixed $id): string => Asset::query()->find($id)?->name ?? '#'.$id,
            'panel_mcb_id' => fn (mixed $id): string => PanelMcb::query()->find($id)?->mcb_number ?? '#'.$id,
            'panel_mcb_id_2' => fn (mixed $id): string => PanelMcb::query()->find($id)?->mcb_number ?? '#'.$id,
        ]);

        if ($parts === []) {
            return 'updated customer "'.$this->customer_name.'"';
        }

        return 'updated customer "'.$this->customer_name.'": '.implode(', ', $parts);
    }
}
