<?php

namespace App\Models;

use App\Concerns\DescribesActivityChanges;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use DescribesActivityChanges, HasFactory, LogsActivity;

    protected $fillable = [
        'parent_id',
        'name',
    ];

    /**
     * The default asset categories seeded into the database.
     *
     * @var list<string>
     */
    public const DEFAULTS = [
        'UPS Frame',
        'UPS Modul',
        'UPS Electronic',
        'Battery',
        'PAC',
        'AC',
        'Akses Pintu',
        'Network',
        'DVR',
        'NVR',
        'Kamera',
        'EMS',
        'Sensor TH',
        'FSS Panel',
        'Smoke Detector',
        'Tabung FSS',
        'Evacuated Lamp',
        'FSS Horn',
        'FSS Siren',
        'FSS Manual Release',
        'FSS Manual Abort',
        'APAR',
        'Genset',
        'Monitor',
        'Panel Power',
        'Rectifire',
        'PC',
        'Server',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * Find the ids of every category whose name matches exactly, plus all of
     * their descendants. Used by the customer switch dropdown, the panel
     * power list, and CustomerRequest's panel validation (a FormRequest has
     * no controller to share a helper with).
     *
     * @return list<int>
     */
    public static function idsByName(string $name): array
    {
        $categories = self::query()->get(['id', 'name', 'parent_id']);

        $childrenByParent = $categories
            ->groupBy(fn (Category $category) => $category->parent_id ?? 0);

        $matched = $categories
            ->filter(fn (Category $category) => $category->name === $name)
            ->pluck('id');

        $ids = $matched->all();

        $collectDescendants = function (int $parentId) use (&$collectDescendants, $childrenByParent, &$ids) {
            foreach ($childrenByParent->get($parentId, collect()) as $child) {
                $ids[] = $child->id;
                $collectDescendants($child->id);
            }
        };

        foreach ($matched as $id) {
            $collectDescendants($id);
        }

        return array_values(array_unique($ids));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('category')
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(function (string $eventName): string {
                $parent = $this->describeCategoryParent();

                return match ($eventName) {
                    'created' => 'created category "'.$this->name.'"'.$parent,
                    'updated' => $this->describeCategoryUpdate($parent),
                    'deleted' => 'deleted category "'.$this->name.'"'.$parent,
                    default => $eventName,
                };
            });
    }

    /**
     * The parent label used in every description: ` on "Parent"` for a
     * sub-category, or an empty string for a top-level one. The name is
     * looked up fresh: the `parent` relation may already be cached from
     * the "created" description and would then be stale.
     */
    protected function describeCategoryParent(): string
    {
        if ($this->parent_id === null) {
            return '';
        }

        $name = self::query()->find($this->parent_id)?->name;

        return $name !== null ? ' on "'.$name.'"' : '';
    }

    /**
     * Build a description listing each changed attribute and its old/new value.
     */
    protected function describeCategoryUpdate(string $parent): string
    {
        $parts = $this->describeChangedAttributes([
            'parent_id' => fn (mixed $id): string => self::query()->find($id)?->name ?? (string) $id,
        ]);

        if ($parts === []) {
            return 'updated category "'.$this->name.'"'.$parent;
        }

        return 'updated category "'.$this->name.'"'.$parent.': '.implode(', ', $parts);
    }
}
