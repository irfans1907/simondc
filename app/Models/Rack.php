<?php

namespace App\Models;

use App\Concerns\DescribesActivityChanges;
use Database\Factories\RackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Rack extends Model
{
    /** @use HasFactory<RackFactory> */
    use DescribesActivityChanges, HasFactory, LogsActivity;

    /**
     * Allowed PDU outlet states.
     *
     * @var list<string>
     */
    public const PDU_STATES = ['Ada', 'Tidak Ada'];

    /**
     * Default rack status until the customer module drives it.
     */
    public const DEFAULT_STATUS = 'Ready For Sale';

    /**
     * Rack status once a customer is assigned to it (set and cleared by
     * CustomerController's store/update/destroy).
     */
    public const OCCUPIED_STATUS = 'Occupied';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'rack_code',
        'floor',
        'room',
        'status',
        'brand',
        'type',
        'capacity',
        'dimension',
        'pdu_outlet_1',
        'pdu_outlet_2',
        'manufacture_year',
        'operation_year',
        'notes',
    ];

    /**
     * The customers assigned to this rack (at most one, enforced by the
     * unique rack_id on customers).
     *
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('rack')
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(function (string $eventName): string {
                $context = $this->describeRackContext();

                return match ($eventName) {
                    'created' => 'created rack "'.$this->rack_code.'"'.$context,
                    'updated' => $this->describeRackUpdate(),
                    'deleted' => 'deleted rack "'.$this->rack_code.'"'.$context,
                    default => $eventName,
                };
            });
    }

    /**
     * The type/floor/room context appended to the create and delete
     * descriptions, mirroring the category context on assets.
     */
    protected function describeRackContext(): string
    {
        return sprintf(
            ' (type "%s", floor %s, room %s)',
            $this->type,
            $this->floor,
            $this->room,
        );
    }

    /**
     * Build a description listing each changed attribute and its old/new value.
     */
    protected function describeRackUpdate(): string
    {
        $parts = $this->describeChangedAttributes();

        if ($parts === []) {
            return 'updated rack "'.$this->rack_code.'"';
        }

        return 'updated rack "'.$this->rack_code.'": '.implode(', ', $parts);
    }
}
