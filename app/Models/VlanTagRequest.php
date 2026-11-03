<?php

namespace App\Models;

use App\Concerns\DescribesActivityChanges;
use Database\Factories\VlanTagRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class VlanTagRequest extends Model
{
    /** @use HasFactory<VlanTagRequestFactory> */
    use DescribesActivityChanges, HasFactory, LogsActivity;

    /**
     * Allowed VLAN tag request modes. LAG is the only mode allowed to
     * carry more than one switch (each switch contributes its own port).
     *
     * @var list<string>
     */
    public const MODES = ['TRUNK', 'ACCESS', 'QINQ', 'LAG'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'order_number',
        'sid',
        'service_name',
        'vlan',
        'mode',
    ];

    /**
     * The customer this request belongs to.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The switch rows of this request: one port on one switch each.
     *
     * @return HasMany<VlanTagRequestSwitch, $this>
     */
    public function switches(): HasMany
    {
        return $this->hasMany(VlanTagRequestSwitch::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('vlan_tag_request')
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(function (string $eventName): string {
                $context = $this->describeVlanTagRequestContext();

                return match ($eventName) {
                    'created' => 'created VLAN tag request "'.$this->order_number.'"'.$context,
                    'updated' => $this->describeVlanTagRequestUpdate(),
                    'deleted' => 'deleted VLAN tag request "'.$this->order_number.'"'.$context,
                    default => $eventName,
                };
            });
    }

    /**
     * The customer context appended to the create and delete descriptions.
     * The name is looked up fresh: the `customer` relation may already be
     * cached from an earlier description and would then be stale.
     */
    protected function describeVlanTagRequestContext(): string
    {
        if ($this->customer_id === null) {
            return ' for customer "no customer"';
        }

        $name = Customer::query()->find($this->customer_id)?->customer_name;

        return ' for customer "'.($name ?? '#'.$this->customer_id).'"';
    }

    /**
     * Build a description listing each changed attribute and its old/new value.
     */
    protected function describeVlanTagRequestUpdate(): string
    {
        $parts = $this->describeChangedAttributes([
            'customer_id' => fn (mixed $id): string => Customer::query()->find($id)?->customer_name ?? '#'.$id,
        ], [
            'customer_id' => 'customer',
        ]);

        if ($parts === []) {
            return 'updated VLAN tag request "'.$this->order_number.'"';
        }

        return 'updated VLAN tag request "'.$this->order_number.'": '.implode(', ', $parts);
    }
}
