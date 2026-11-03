<?php

namespace App\Models;

use App\Concerns\DescribesActivityChanges;
use Database\Factories\RackTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class RackType extends Model
{
    /** @use HasFactory<RackTypeFactory> */
    use DescribesActivityChanges, HasFactory, LogsActivity;

    /**
     * The default rack types seeded for a fresh install.
     *
     * @var list<array{name: string, capacity: string}>
     */
    public const DEFAULTS = [
        ['name' => 'U Rack', 'capacity' => '1U'],
        ['name' => 'Sub Rack', 'capacity' => '15U'],
        ['name' => 'Full Rack Server', 'capacity' => '42U'],
        ['name' => 'Full Rack Network', 'capacity' => '45U'],
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'capacity',
    ];

    /**
     * The racks currently using this type.
     *
     * @return HasMany<Rack, $this>
     */
    public function racks(): HasMany
    {
        return $this->hasMany(Rack::class, 'type', 'name');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('rack_type')
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(function (string $eventName): string {
                $context = $this->describeRackTypeContext();

                return match ($eventName) {
                    'created' => 'created rack type "'.$this->name.'"'.$context,
                    'updated' => $this->describeRackTypeUpdate($context),
                    'deleted' => 'deleted rack type "'.$this->name.'"'.$context,
                    default => $eventName,
                };
            });
    }

    /**
     * The capacity context appended to the create and delete descriptions.
     */
    protected function describeRackTypeContext(): string
    {
        return sprintf(' (capacity %s)', $this->capacity);
    }

    /**
     * Build a description listing each changed attribute and its old/new value.
     */
    protected function describeRackTypeUpdate(string $context): string
    {
        $parts = $this->describeChangedAttributes();

        if ($parts === []) {
            return 'updated rack type "'.$this->name.'"'.$context;
        }

        return 'updated rack type "'.$this->name.'": '.implode(', ', $parts);
    }
}
