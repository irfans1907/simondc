<?php

namespace App\Helpers;

use App\Models\Asset;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class AssetSheetHelper
{
    /**
     * Columns searched by the Asset List search box. Sheet-specific JSON
     * details are intentionally excluded.
     *
     * @var list<string>
     */
    private const SEARCH_COLUMNS = [
        'name',
        'alias',
        'ip_address',
        'brand',
        'type',
        'model',
        'serial_number',
        'capacity',
        'room',
        'floor',
        'owner',
        'notes',
    ];

    /**
     * @return array{default: string, sheets: array<string, array<string, mixed>>}
     */
    public static function config(): array
    {
        return config('asset_list_sheets');
    }

    /**
     * Resolve a requested sheet to a known key, falling back to the default.
     */
    public static function sheetKey(?string $sheet): string
    {
        $config = self::config();

        return array_key_exists((string) $sheet, $config['sheets'])
            ? (string) $sheet
            : $config['default'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sheet(string $sheet): array
    {
        return self::config()['sheets'][self::sheetKey($sheet)];
    }

    /**
     * Resolve every column with its storage target, required flag, and hint.
     *
     * @return list<array{key: string, label: string, storage: string, required: bool, computed: bool, hint: string|null}>
     */
    public static function columns(string $sheet): array
    {
        $config = self::sheet($sheet);
        $storage = $config['storage'] ?? [];
        $required = $config['required'] ?? [];
        $countKeys = $config['counts'] ?? [];
        $computed = self::config()['computed'] ?? [];

        return collect($config['columns'])
            ->map(fn (array $column): array => [
                'key' => $column['key'],
                'label' => $column['label'],
                'storage' => $storage[$column['key']] ?? 'details',
                'required' => in_array($column['key'], $required, true),
                'computed' => in_array($column['key'], $computed, true),
                'hint' => in_array($column['key'], $countKeys, true)
                    ? 'Format lantai:jumlah, contoh 2:5, 3:3.'
                    : null,
            ])
            ->all();
    }

    /**
     * Column keys that hold a floor:count list.
     *
     * @return list<string>
     */
    public static function countKeys(string $sheet): array
    {
        return self::sheet($sheet)['counts'] ?? [];
    }

    /**
     * Column keys stored in the assets.details JSON column.
     *
     * @return list<string>
     */
    public static function detailKeys(string $sheet): array
    {
        return collect(self::columns($sheet))
            ->where('storage', 'details')
            ->where('computed', false)
            ->pluck('key')
            ->values()
            ->all();
    }

    /**
     * Columns shown in the create/edit form. Sheets without form groups show
     * every non-computed column. Sheets with groups split the columns into
     * shared fields plus per-subcategory extras.
     *
     * @return array{common: list<string>, groups: list<array{name: string, keys: list<string>, keywords: list<string>}>}|null
     */
    public static function formGroups(string $sheet): ?array
    {
        $config = self::sheet($sheet);
        $groups = $config['form_groups'] ?? [];

        if ($groups === []) {
            return null;
        }

        $keywords = $config['form_group_keywords'] ?? [];
        $columns = self::columns($sheet);
        $groupKeys = collect($groups)->flatten()->all();

        return [
            'common' => collect($columns)
                ->reject(fn (array $column): bool => $column['computed']
                    || in_array($column['key'], $groupKeys, true))
                ->pluck('key')
                ->values()
                ->all(),
            'groups' => collect($groups)
                ->map(fn (array $keys, string $name): array => [
                    'name' => $name,
                    'keys' => array_values($keys),
                    'keywords' => array_values($keywords[$name] ?? [mb_strtolower($name)]),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Keywords that mark a category as a battery category.
     *
     * @return list<string>
     */
    public static function batteryKeywords(string $sheet): array
    {
        return self::sheet($sheet)['battery_keywords'] ?? [];
    }

    /**
     * Whether a category name belongs to the battery group.
     */
    public static function isBatteryCategoryName(string $sheet, ?string $name): bool
    {
        $name = mb_strtolower(trim((string) $name));

        if ($name === '') {
            return false;
        }

        foreach (self::batteryKeywords($sheet) as $keyword) {
            if (str_contains($name, mb_strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether any submitted category level marks the asset as a battery.
     *
     * @param  array<int, mixed>  $path
     */
    public static function pathIsBattery(string $sheet, array $path): bool
    {
        foreach ($path as $level) {
            if (is_string($level) && self::isBatteryCategoryName($sheet, $level)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether any submitted category level marks the asset as a camera.
     *
     * @param  array<int, mixed>  $path
     */
    public static function pathIsCamera(string $sheet, array $path): bool
    {
        $cameraName = mb_strtolower(trim((string) (self::sheet($sheet)['camera_category'] ?? '')));

        if ($cameraName === '') {
            return false;
        }

        foreach ($path as $level) {
            if (is_string($level) && mb_strtolower(trim($level)) === $cameraName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Category ids treated as battery categories, including their descendants.
     *
     * @return list<int>
     */
    public static function batteryCategoryIds(string $sheet): array
    {
        $categories = Category::query()->get(['id', 'name', 'parent_id']);

        if ($categories->isEmpty()) {
            return [];
        }

        $byId = $categories->keyBy('id');

        return $categories
            ->filter(function (Category $category) use ($byId, $sheet): bool {
                $current = $category;

                while ($current !== null) {
                    if (self::isBatteryCategoryName($sheet, $current->name)) {
                        return true;
                    }

                    $current = $current->parent_id === null
                        ? null
                        : $byId->get($current->parent_id);
                }

                return false;
            })
            ->pluck('id')
            ->values()
            ->all();
    }

    /**
     * Split-table layout for the sheet. Null when the sheet renders one table.
     *
     * @return array<string, array{label: string, columns: list<array{key: string, label: string, storage: string, required: bool, computed: bool, hint: string|null}>, shared: list<string>, identity: list<string>, hidden: list<string>}>|null
     */
    public static function tables(string $sheet): ?array
    {
        $tables = self::sheet($sheet)['tables'] ?? [];

        if ($tables === []) {
            return null;
        }

        $columns = collect(self::columns($sheet))->keyBy('key');

        return collect($tables)
            ->map(fn (array $table): array => [
                'label' => $table['label'],
                'columns' => collect($table['columns'])
                    ->map(fn (string $key) => $columns->get($key))
                    ->filter()
                    ->values()
                    ->all(),
                'shared' => array_values($table['shared'] ?? []),
                'identity' => array_values($table['identity'] ?? []),
                'hidden' => array_values($table['hidden_columns'] ?? []),
            ])
            ->all();
    }

    /**
     * Grouped-table layout for the sheet. Null when rows are not grouped.
     * `group_by` decides which rows merge (defaults to the identity columns);
     * `per_row_identity` lists identity columns that stay visible on every
     * row instead of being merged into the first one.
     *
     * @return array{identity: list<string>, group_by: list<string>, per_row_identity: list<string>}|null
     */
    public static function rowGroup(string $sheet): ?array
    {
        $group = self::sheet($sheet)['row_group'] ?? [];

        if ($group === []) {
            return null;
        }

        $identity = array_values($group['identity'] ?? []);

        return [
            'identity' => $identity,
            'group_by' => array_values($group['group_by'] ?? $identity),
            'per_row_identity' => array_values($group['per_row_identity'] ?? []),
        ];
    }

    /**
     * Columns copied into the form when an existing name is picked. An
     * explicit `autofill` list wins; otherwise reuse the row_group identity.
     *
     * @return list<string>
     */
    public static function autofill(string $sheet): array
    {
        $config = self::sheet($sheet);

        if (array_key_exists('autofill', $config)) {
            return array_values($config['autofill']);
        }

        return self::rowGroup($sheet)['identity'] ?? [];
    }

    /**
     * Multi-row create layout for the sheet. Null when the form creates one
     * asset per submit.
     *
     * @return array{label: string, keys: list<string>}|null
     */
    public static function multiRow(string $sheet): ?array
    {
        $multi = self::sheet($sheet)['multi_row'] ?? [];

        if ($multi === []) {
            return null;
        }

        return [
            'label' => (string) ($multi['label'] ?? 'Baris'),
            'keys' => array_values($multi['keys'] ?? []),
        ];
    }

    /**
     * Category ids (including descendants) that feed the sheet. An empty
     * list means the sheet shows every asset.
     *
     * @return list<int>
     */
    public static function categoryIds(string $sheet): array
    {
        $names = self::sheet($sheet)['categories'] ?? [];

        if ($names === []) {
            return [];
        }

        return collect($names)
            ->flatMap(fn (string $name): array => Category::idsByName($name))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Whether the asset is shown as one grouped row in Asset Settings. The
     * UPS sheet collapses same-name assets (frame, module, battery) into a
     * single row, so they also share one QR sticker and checklist.
     */
    public static function isGroupedAsset(Asset $asset): bool
    {
        return in_array($asset->category_id, self::categoryIds('ups-batt'), true);
    }

    /**
     * Whether the asset belongs to the CCTV sheet (DVR, NVR, or camera).
     */
    public static function isCctvAsset(Asset $asset): bool
    {
        return in_array($asset->category_id, self::categoryIds('cctv'), true);
    }

    /**
     * Category ids that hold individual camera channels. A device (DVR/NVR)
     * keeps the device fields; its cameras live in this category and point
     * back through parent_asset_id.
     *
     * @return list<int>
     */
    public static function cctvCameraCategoryIds(): array
    {
        $name = trim((string) (self::sheet('cctv')['camera_category'] ?? ''));

        if ($name === '') {
            return [];
        }

        $sheetCategoryIds = self::categoryIds('cctv');

        if ($sheetCategoryIds === []) {
            return [];
        }

        return Category::query()
            ->where('name', $name)
            ->whereIn('id', $sheetCategoryIds)
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('id')
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>|null  $cameraCategoryIds
     */
    public static function isCctvCameraCategory(int $categoryId, ?array $cameraCategoryIds = null): bool
    {
        $cameraCategoryIds ??= self::cctvCameraCategoryIds();

        return in_array($categoryId, $cameraCategoryIds, true);
    }

    /**
     * A camera channel asset: it sits in the camera category and belongs to
     * a device through parent_asset_id.
     *
     * @param  list<int>|null  $cameraCategoryIds
     */
    public static function isCctvCamera(Asset $asset, ?array $cameraCategoryIds = null): bool
    {
        return $asset->parent_asset_id !== null
            && self::isCctvCameraCategory((int) $asset->category_id, $cameraCategoryIds);
    }

    /**
     * A CCTV device (DVR/NVR): the asset that owns the checklist and QR.
     *
     * @param  list<int>|null  $cameraCategoryIds
     * @param  list<int>|null  $cctvCategoryIds
     */
    public static function isCctvDeviceCategory(
        int $categoryId,
        ?array $cameraCategoryIds = null,
        ?array $cctvCategoryIds = null,
    ): bool {
        $cctvCategoryIds ??= self::categoryIds('cctv');

        return in_array($categoryId, $cctvCategoryIds, true)
            && ! self::isCctvCameraCategory($categoryId, $cameraCategoryIds);
    }

    /**
     * Identity of the device an asset belongs to. Assets sharing a key are
     * shown as one row in Asset Settings and share one QR sticker, so the
     * category list counts them once too: UPS groups by name, CCTV devices
     * by name and asset category. Camera channels are real assets now, so
     * each one is counted on its own.
     *
     * Callers that resolve many keys can pass the category id lists they
     * already loaded to avoid a category query per asset.
     *
     * @param  list<int>|null  $upsCategoryIds
     * @param  list<int>|null  $cctvCategoryIds
     * @param  list<int>|null  $cameraCategoryIds
     */
    public static function deviceGroupKey(
        Asset $asset,
        ?array $upsCategoryIds = null,
        ?array $cctvCategoryIds = null,
        ?array $cameraCategoryIds = null,
    ): string {
        $upsCategoryIds ??= self::categoryIds('ups-batt');
        $cctvCategoryIds ??= self::categoryIds('cctv');
        $cameraCategoryIds ??= self::cctvCameraCategoryIds();

        if (in_array($asset->category_id, $upsCategoryIds, true)) {
            return 'ups:'.mb_strtolower(trim($asset->name));
        }

        if (in_array($asset->category_id, $cctvCategoryIds, true)
            && ! self::isCctvCameraCategory((int) $asset->category_id, $cameraCategoryIds)) {
            return 'cctv:'.mb_strtolower(trim($asset->name)).'|'.$asset->category_id;
        }

        return 'asset:'.$asset->id;
    }

    /**
     * Grouping key for one CCTV row in Asset Settings and Asset List: a
     * device and all of its camera channels share it. Legacy channel records
     * (stored without a parent) still group by name and category.
     *
     * @param  list<int>|null  $cameraCategoryIds
     */
    public static function cctvDeviceKey(Asset $asset, ?array $cameraCategoryIds = null): string
    {
        $cameraCategoryIds ??= self::cctvCameraCategoryIds();

        if (self::isCctvCamera($asset, $cameraCategoryIds) && $asset->parent !== null) {
            return 'cctv:'.mb_strtolower(trim($asset->parent->name)).'|'.$asset->parent->category_id;
        }

        return 'cctv:'.mb_strtolower(trim($asset->name)).'|'.$asset->category_id;
    }

    /**
     * Columns that identify one physical CCTV device. Records sharing these
     * values are the same DVR/NVR even when other fields differ.
     *
     * @return list<string>
     */
    public static function cctvDeviceIdentityColumns(): array
    {
        return ['name', 'category_id', 'brand', 'ip_address', 'serial_number', 'model'];
    }

    /**
     * Identity key for an existing CCTV device.
     */
    public static function cctvDeviceIdentityKey(Asset $asset): string
    {
        return self::cctvIdentityKeyFromValues([
            'name' => $asset->name,
            'category_id' => $asset->category_id,
            'brand' => $asset->brand,
            'ip_address' => $asset->ip_address,
            'serial_number' => $asset->serial_number,
            'model' => $asset->model,
        ]);
    }

    /**
     * Identity key from submitted/attribute values.
     *
     * @param  array<string, mixed>  $values
     */
    public static function cctvIdentityKeyFromValues(array $values): string
    {
        return collect(self::cctvDeviceIdentityColumns())
            ->map(fn (string $column): string => mb_strtolower(trim((string) ($values[$column] ?? ''))))
            ->implode('|');
    }

    /**
     * Merge HDD slot capacity strings into one unique, semicolon separated
     * value: "SLOT-2 : 2TB; SLOT-3 : 1TB".
     */
    public static function mergeCapacityValues(iterable $values): string
    {
        return self::normalizeCapacityValue(collect($values)->implode('; '));
    }

    /**
     * Normalise a capacity entry written over multiple lines or separated by
     * semicolons into the stored "slot; slot" form.
     */
    public static function normalizeCapacityValue(?string $value): string
    {
        return collect(preg_split('/[;\r\n]+/', (string) $value) ?: [])
            ->map(fn (string $part): string => trim($part))
            ->filter(fn (string $part): bool => $part !== '')
            ->unique(fn (string $part): string => mb_strtolower($part))
            ->implode('; ');
    }

    /**
     * Camera assets are named after the camera alias so they read naturally
     * in the camera category, falling back to the channel number and then
     * the device name.
     *
     * @param  array<string, mixed>  $details
     */
    public static function cctvCameraName(array $details, string $fallback): string
    {
        foreach (['alias_kamera', 'camera_no'] as $key) {
            $value = trim((string) ($details[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    }

    /**
     * The name shown in Asset Settings, the QR label, and the scan page.
     * FAP rows all share the panel name "FM100", so the system detail
     * (NN100.A / NN100.B) is appended. CCTV devices append their category so
     * channels read as "CCTV-1 DVR" instead of just "CCTV-1".
     */
    public static function displayName(Asset $asset): string
    {
        $system = trim((string) ($asset->details['system'] ?? ''));

        if ($system !== '') {
            return "{$asset->name} - {$system}";
        }

        if (self::isCctvAsset($asset)) {
            $category = trim((string) $asset->category?->name);

            return $category === '' ? $asset->name : "{$asset->name} {$category}";
        }

        return $asset->name;
    }

    /**
     * Every asset that shares the settings row of the given asset.
     * Non-grouped assets are their own group.
     *
     * @return Collection<int, Asset>
     */
    public static function assetGroupMembers(Asset $asset): Collection
    {
        $cameraCategoryIds = self::cctvCameraCategoryIds();

        if (self::isCctvCamera($asset, $cameraCategoryIds)
            || self::isCctvDeviceCategory((int) $asset->category_id, $cameraCategoryIds)) {
            return self::cctvDeviceMembers($asset, $cameraCategoryIds);
        }

        if (! self::isGroupedAsset($asset)) {
            return new Collection([$asset]);
        }

        return self::assetGroupMembersByName($asset->name);
    }

    /**
     * The device and its camera channels, ordered with the device first so
     * the shared identity columns are read from it.
     *
     * @param  list<int>  $cameraCategoryIds
     * @return Collection<int, Asset>
     */
    private static function cctvDeviceMembers(Asset $asset, array $cameraCategoryIds): Collection
    {
        $device = self::isCctvCamera($asset, $cameraCategoryIds)
            ? $asset->parent
            : $asset;

        if ($device === null) {
            return new Collection([$asset]);
        }

        return Asset::query()
            ->with('category')
            ->where(function (Builder $query) use ($device): void {
                $query->where('id', $device->id)
                    ->orWhere('parent_asset_id', $device->id)
                    // Legacy channel records were stored directly under the
                    // device identity before cameras became their own assets.
                    ->orWhere(function (Builder $legacy) use ($device): void {
                        $legacy->whereNull('parent_asset_id')
                            ->where('name', $device->name)
                            ->where('category_id', $device->category_id);
                    });
            })
            ->orderByRaw('case when parent_asset_id is null then 0 else 1 end')
            ->orderBy('id')
            ->get();
    }

    /**
     * CCTV device members for a sticker that only carries the device name
     * and category. Camera-only stickers resolve to their own channels.
     *
     * @return Collection<int, Asset>
     */
    public static function assetGroupMembersByNameAndCategory(
        string $name,
        int $categoryId,
    ): Collection {
        $name = trim($name);
        $cctvCategoryIds = self::categoryIds('cctv');

        if ($name === '' || ! in_array($categoryId, $cctvCategoryIds, true)) {
            return new Collection;
        }

        $cameraCategoryIds = self::cctvCameraCategoryIds();

        if (self::isCctvCameraCategory($categoryId, $cameraCategoryIds)) {
            return Asset::query()
                ->with('category')
                ->where('name', $name)
                ->where('category_id', $categoryId)
                ->orderBy('id')
                ->get();
        }

        $device = Asset::query()
            ->with('category')
            ->where('name', $name)
            ->where('category_id', $categoryId)
            ->whereNull('parent_asset_id')
            ->orderBy('id')
            ->first();

        if ($device === null) {
            return new Collection;
        }

        return self::cctvDeviceMembers($device, $cameraCategoryIds);
    }

    /**
     * The sheet-specific identity of a grouped asset: the JSON detail values
     * that tell its members apart (module number/serial, battery bank, ...).
     * Base columns such as model and serial_number are empty for UPS rows.
     *
     * @return list<array{label: string, value: string}>
     */
    public static function assetDetailFields(Asset $asset): array
    {
        $isCctv = self::isCctvAsset($asset);

        if (! self::isGroupedAsset($asset) && ! $isCctv) {
            return [];
        }

        $details = $asset->details ?? [];

        if (! is_array($details) || $details === []) {
            return [];
        }

        $columns = collect(self::columns($isCctv ? 'cctv' : 'ups-batt'))->keyBy('key');

        return collect(self::detailKeysFor($asset))
            ->map(function (string $key) use ($details, $columns): ?array {
                $value = trim((string) ($details[$key] ?? ''));

                if ($value === '') {
                    return null;
                }

                $label = (string) ($columns->get($key)['label'] ?? $key);
                $shortLabel = str_contains($label, ' - ')
                    ? explode(' - ', $label, 2)[1]
                    : $label;

                return ['label' => $shortLabel, 'value' => $value];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Detail keys that belong to the member's own category. A module row also
     * carries the frame values, which would otherwise repeat on every module.
     *
     * @return list<string>
     */
    private static function detailKeysFor(Asset $asset): array
    {
        $category = mb_strtolower(trim((string) $asset->category?->name));

        return match (true) {
            str_contains($category, 'modul') => [
                'modul_nomor',
                'modul_sn',
                'modul_kva',
                'modul_tahun',
            ],
            str_contains($category, 'batter') => [
                'batterai_bank_no',
                'batterai_merek',
                'batterai_model',
                'batterai_series',
                'batterai_v_ah',
                'batterai_jumlah',
            ],
            str_contains($category, 'frame') => [
                'frame_nomor',
                'frame_tahun',
                'frame_sn',
            ],
            str_contains($category, 'kamera') => [
                'camera_no',
                'alias_kamera',
                'merek_kamera',
                'model_type',
                'sn',
                'analog_digital',
                'ip_kamera',
                'lantai_kamera',
            ],
            default => [],
        };
    }

    /**
     * Assets that make up the UPS group with the given name, ordered by id
     * so the first member stays a stable representative.
     *
     * @return Collection<int, Asset>
     */
    public static function assetGroupMembersByName(string $name): Collection
    {
        $name = trim($name);

        if ($name === '') {
            return new Collection;
        }

        return Asset::query()
            ->with('category')
            ->where('name', $name)
            ->whereIn('category_id', self::categoryIds('ups-batt'))
            ->orderBy('id')
            ->get();
    }

    /**
     * Restrict the query to the sheet's category subtree.
     */
    public static function filterBySheet(Builder $query, string $sheet): Builder
    {
        $categoryIds = self::categoryIds($sheet);

        return $categoryIds === []
            ? $query
            : $query->whereIn('category_id', $categoryIds);
    }

    /**
     * Apply the free-text search to the shared asset columns.
     */
    public static function search(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $categoryIds = self::categoryIdsMatching($search);

        return $query->where(function (Builder $query) use ($search, $categoryIds): void {
            foreach (self::SEARCH_COLUMNS as $column) {
                $query->orWhere($column, 'like', '%'.$search.'%');
            }

            if ($categoryIds !== []) {
                $query->orWhereIn('category_id', $categoryIds);
            }
        });
    }

    /**
     * Category ids whose name contains the search term, plus all of their
     * descendants. Used so the generated Keterangan (name + category path)
     * is searchable through its category parts.
     *
     * @return list<int>
     */
    public static function categoryIdsMatching(string $search): array
    {
        $categories = Category::query()->get(['id', 'name', 'parent_id']);

        $childrenByParent = $categories
            ->groupBy(fn (Category $category) => $category->parent_id ?? 0);

        $matched = $categories
            ->filter(fn (Category $category) => mb_stripos($category->name, $search) !== false)
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

    /**
     * Map an asset to the values of the sheet's columns, keyed by column key.
     *
     * @param  list<array{key: string, label: string, storage: string, required: bool, computed: bool}>  $columns
     * @param  list<string>  $categoryPath
     * @return array<string, mixed>
     */
    public static function values(Asset $asset, array $columns, array $categoryPath = []): array
    {
        $details = $asset->details ?? [];
        $values = [];

        foreach ($columns as $column) {
            if ($column['computed'] ?? false) {
                $values[$column['key']] = match ($column['key']) {
                    'keterangan' => self::description($asset, $categoryPath),
                    'kategori' => self::categoryLabel($categoryPath),
                    default => '',
                };

                continue;
            }

            $values[$column['key']] = $column['storage'] === 'details'
                ? ($details[$column['key']] ?? '')
                : ($asset->{$column['storage']} ?? '');
        }

        return $values;
    }

    /**
     * Compose the generated Keterangan text: asset name plus its category path.
     *
     * @param  list<string>  $categoryPath
     */
    public static function description(Asset $asset, array $categoryPath): string
    {
        $parts = array_filter(
            [$asset->name, ...$categoryPath],
            fn (?string $part): bool => trim((string) $part) !== '',
        );

        return implode("\n", $parts);
    }

    /**
     * Compose the generated Kategori text from the category path.
     *
     * @param  list<string>  $categoryPath
     */
    public static function categoryLabel(array $categoryPath): string
    {
        $parts = array_filter(
            $categoryPath,
            fn (?string $part): bool => trim((string) $part) !== '',
        );

        return implode(' > ', $parts);
    }

    /**
     * Normalise a "floor:count, floor:count" string. A bare floor means 1.
     * Duplicate floors are summed and the result is sorted naturally.
     *
     * @throws InvalidArgumentException when a segment is malformed
     */
    public static function normalizeFloorCounts(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $counts = [];

        foreach (preg_split('/[,;]+/', $value) ?: [] as $segment) {
            $segment = trim($segment);

            if ($segment === '') {
                continue;
            }

            if (preg_match('/^(?<floor>[^:]+):(?<count>\d+)$/', $segment, $matches)) {
                $floor = trim($matches['floor']);
                $count = (int) $matches['count'];
            } elseif (preg_match('/^(?<floor>[^:]+)$/', $segment, $matches)) {
                $floor = trim($matches['floor']);
                $count = 1;
            } else {
                throw new InvalidArgumentException('Invalid floor count segment: '.$segment);
            }

            if ($floor === '' || $count < 1) {
                throw new InvalidArgumentException('Invalid floor count segment: '.$segment);
            }

            $counts[$floor] = ($counts[$floor] ?? 0) + $count;
        }

        if ($counts === []) {
            return '';
        }

        uksort(
            $counts,
            fn (int|string $left, int|string $right): int => strnatcasecmp((string) $left, (string) $right),
        );

        return implode(', ', array_map(
            fn (int|string $floor, int $count): string => $floor.':'.$count,
            array_keys($counts),
            array_values($counts),
        ));
    }

    /**
     * Parse a floor:count string into a floor => count map.
     *
     * @return array<int|string, int>
     */
    public static function floorCounts(?string $value): array
    {
        $normalized = self::normalizeFloorCounts($value);

        if ($normalized === '') {
            return [];
        }

        $counts = [];

        foreach (explode(',', $normalized) as $segment) {
            [$floor, $count] = explode(':', trim($segment));
            $counts[trim($floor)] = (int) $count;
        }

        return $counts;
    }

    /**
     * Whether the root category name is allowed for the sheet. General
     * (empty category list) accepts every category.
     */
    public static function categoryAllowed(string $sheet, string $rootName): bool
    {
        $allowed = self::sheet($sheet)['categories'] ?? [];

        if ($allowed === []) {
            return true;
        }

        return in_array(
            mb_strtolower(trim($rootName)),
            array_map(fn (string $name): string => mb_strtolower($name), $allowed),
            true,
        );
    }

    /**
     * Sheet label for the given key.
     */
    public static function sheetLabel(string $sheet): string
    {
        return self::config()['sheets'][$sheet]['label'] ?? $sheet;
    }

    /**
     * Root category names managed by a non-General sheet.
     *
     * @return list<string>
     */
    public static function categoryRootsManagedBySheets(): array
    {
        $default = self::config()['default'];

        return collect(self::config()['sheets'])
            ->except($default)
            ->flatMap(fn (array $sheet): array => $sheet['categories'] ?? [])
            ->map(fn (string $name): string => mb_strtolower($name))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Whether the root category is managed from a non-General sheet.
     */
    public static function categoryManagedByOtherSheet(string $rootName): bool
    {
        return in_array(
            mb_strtolower(trim($rootName)),
            self::categoryRootsManagedBySheets(),
            true,
        );
    }

    /**
     * Sheet label that manages the given root category, if any.
     */
    public static function managedSheetLabelForCategoryName(string $rootName): ?string
    {
        $default = self::config()['default'];

        foreach (self::config()['sheets'] as $slug => $sheet) {
            if ($slug === $default) {
                continue;
            }

            foreach ($sheet['categories'] ?? [] as $name) {
                if (mb_strtolower($name) === mb_strtolower(trim($rootName))) {
                    return $sheet['label'];
                }
            }
        }

        return null;
    }

    /**
     * Map category id => sheet slug for every category managed by a
     * non-General sheet.
     *
     * @return array<int, string>
     */
    public static function managedSheetMap(): array
    {
        $default = self::config()['default'];
        $map = [];

        foreach (self::config()['sheets'] as $slug => $sheet) {
            if ($slug === $default) {
                continue;
            }

            foreach (self::categoryIds($slug) as $categoryId) {
                $map[$categoryId] ??= $slug;
            }
        }

        return $map;
    }

    /**
     * Build the category tree, restricted to the sheet's roots when the
     * sheet maps to specific categories. General excludes every root
     * managed by another sheet so new assets are created where they are
     * editable.
     *
     * @return list<array{id: int, name: string, children: list<array<string, mixed>>}>
     */
    public static function categoryTree(string $sheet): array
    {
        $allowed = self::sheet($sheet)['categories'] ?? [];

        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        $byParent = $categories->groupBy(fn (Category $category) => $category->parent_id ?? 0);

        $build = function (int $parentId) use (&$build, $byParent): array {
            return $byParent->get($parentId, collect())
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'children' => $build($category->id),
                ])
                ->all();
        };

        $tree = $build(0);

        if ($sheet === self::config()['default']) {
            $managed = self::categoryRootsManagedBySheets();

            return array_values(array_filter(
                $tree,
                fn (array $node): bool => ! in_array(mb_strtolower($node['name']), $managed, true),
            ));
        }

        if ($allowed === []) {
            return $tree;
        }

        $allowedLower = array_map(fn (string $name): string => mb_strtolower($name), $allowed);

        return array_values(array_filter(
            $tree,
            fn (array $node): bool => in_array(mb_strtolower($node['name']), $allowedLower, true),
        ));
    }

    /**
     * Resolve the category path names of an asset.
     *
     * @param  Collection<int, string>  $nameById
     * @param  Collection<int, int|null>  $parentIdById
     * @return list<string>
     */
    public static function categoryPath(Asset $asset, Collection $nameById, Collection $parentIdById): array
    {
        $path = [];
        $categoryId = $asset->category_id;

        while ($categoryId !== null && isset($nameById[$categoryId])) {
            array_unshift($path, $nameById[$categoryId]);
            $categoryId = $parentIdById[$categoryId];
        }

        return $path;
    }
}
