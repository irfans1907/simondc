<?php

namespace App\Models;

use App\Concerns\DescribesActivityChanges;
use Database\Factories\VlanTagRequestSwitchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class VlanTagRequestSwitch extends Model
{
    /** @use HasFactory<VlanTagRequestSwitchFactory> */
    use DescribesActivityChanges, HasFactory, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'vlan_tag_request_id',
        'asset_id',
        'port_number',
    ];

    /**
     * The VLAN tag request this switch row belongs to.
     *
     * @return BelongsTo<VlanTagRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(VlanTagRequest::class, 'vlan_tag_request_id');
    }

    /**
     * The switch asset this row points at (always a Network asset).
     *
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('vlan_tag_request_switch')
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(function (string $eventName): string {
                $context = $this->describeVlanTagRequestSwitchContext();

                return match ($eventName) {
                    'created' => 'created VLAN tag request switch on asset "'.$this->describeAssetName().'"'.$context,
                    'updated' => $this->describeVlanTagRequestSwitchUpdate(),
                    'deleted' => 'deleted VLAN tag request switch on asset "'.$this->describeAssetName().'"'.$context,
                    default => $eventName,
                };
            });
    }

    /**
     * The port context appended to the create and delete descriptions.
     */
    protected function describeVlanTagRequestSwitchContext(): string
    {
        return ' port '.$this->port_number;
    }

    /**
     * Build a description listing each changed attribute and its old/new value.
     */
    protected function describeVlanTagRequestSwitchUpdate(): string
    {
        $parts = $this->describeChangedAttributes([
            'asset_id' => fn (mixed $id): string => Asset::query()->find($id)?->name ?? '#'.$id,
            'port_number' => fn (mixed $value): string => $value ?? 'none',
            'vlan_tag_request_id' => fn (mixed $id): string => VlanTagRequest::query()->find($id)?->order_number ?? '#'.$id,
        ], [
            'asset_id' => 'asset',
            'port_number' => 'port',
            'vlan_tag_request_id' => 'request',
        ]);

        if ($parts === []) {
            return 'updated VLAN tag request switch on asset "'.$this->describeAssetName().'"';
        }

        return 'updated VLAN tag request switch on asset "'.$this->describeAssetName().'": '.implode(', ', $parts);
    }

    /**
     * The related asset's name, looked up fresh (the `asset` relation may
     * be cached from an earlier description), or its id when missing.
     */
    protected function describeAssetName(): string
    {
        if ($this->asset_id === null) {
            return 'no asset';
        }

        return Asset::query()->find($this->asset_id)?->name ?? '#'.$this->asset_id;
    }
}
