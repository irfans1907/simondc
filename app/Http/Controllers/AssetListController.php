<?php

namespace App\Http\Controllers;

use App\Helpers\AssetSheetHelper;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Menu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AssetListController extends Controller
{
    /**
     * Show the asset list page (/assets/list). The selected sheet swaps the
     * columns, filters the rows by category, and drives the create/edit form.
     * Sheets that define a split layout render two tables instead of one.
     */
    public function index(Request $request): InertiaResponse
    {
        $sheet = AssetSheetHelper::sheetKey($request->string('sheet')->toString());
        $columns = AssetSheetHelper::columns($sheet);
        $perPage = max(1, $request->integer('per_page', 10));
        $tables = AssetSheetHelper::tables($sheet);
        $childMenu = Menu::where('url', '/'.$request->path())->first();

        $props = [
            ...$this->childMenuProps($childMenu?->parent, $childMenu),
            'sheets' => collect(AssetSheetHelper::config()['sheets'])
                ->map(fn (array $sheetConfig, string $slug): array => [
                    'slug' => $slug,
                    'label' => $sheetConfig['label'],
                ])
                ->values()
                ->all(),
            'activeSheet' => $sheet,
            'columns' => $columns,
            'formGroups' => AssetSheetHelper::formGroups($sheet),
            'batteryKeywords' => AssetSheetHelper::batteryKeywords($sheet),
            'assetPresets' => $this->assetPresets($sheet),
            'categories' => AssetSheetHelper::categoryTree($sheet),
            'defaultCategory' => AssetSheetHelper::sheet($sheet)['categories'][0] ?? null,
            'cameraCategory' => AssetSheetHelper::sheet($sheet)['camera_category'] ?? null,
            'multiRow' => AssetSheetHelper::multiRow($sheet),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'per_page' => $perPage,
            ],
            'canManage' => $request->user()->hasPermissionTo('manage asset management'),
        ];

        if ($tables !== null) {
            return Inertia::render('dynamic/assets/list', [
                ...$props,
                'tables' => $this->splitTables($request, $sheet, $tables, $perPage),
                'upsOptions' => $this->upsOptions($sheet),
                'assets' => null,
                'groupedTable' => null,
            ]);
        }

        $rowGroup = AssetSheetHelper::rowGroup($sheet);

        if ($rowGroup !== null) {
            return Inertia::render('dynamic/assets/list', [
                ...$props,
                'tables' => null,
                'upsOptions' => [],
                'assets' => null,
                'groupedTable' => [
                    'label' => AssetSheetHelper::sheet($sheet)['label'],
                    'columns' => $columns,
                    'identity' => $rowGroup['identity'],
                    'perRowIdentity' => $rowGroup['per_row_identity'] ?? [],
                    'groups' => $this->groupedPaginator(
                        $request,
                        $sheet,
                        $rowGroup['identity'],
                        $rowGroup['group_by'],
                        $columns,
                        $perPage,
                    ),
                ],
            ]);
        }

        $query = Asset::query();
        AssetSheetHelper::filterBySheet($query, $sheet);
        AssetSheetHelper::search($query, $request->string('search')->toString());

        $assets = $query->orderBy('name')->paginate($perPage)->withQueryString();

        $nameById = Category::query()->pluck('name', 'id');
        $parentIdById = Category::query()->pluck('parent_id', 'id');
        $defaultSheet = AssetSheetHelper::config()['default'];
        $managedSheetMap = $sheet === $defaultSheet
            ? AssetSheetHelper::managedSheetMap()
            : [];

        $assets->through(function (Asset $asset) use ($columns, $nameById, $parentIdById, $managedSheetMap): array {
            $categoryPath = AssetSheetHelper::categoryPath($asset, $nameById, $parentIdById);

            return [
                'id' => $asset->id,
                'parent_asset_id' => $asset->parent_asset_id,
                'category_path' => $categoryPath,
                'managed_sheet' => isset($managedSheetMap[$asset->category_id])
                    ? AssetSheetHelper::sheetLabel($managedSheetMap[$asset->category_id])
                    : null,
                'values' => AssetSheetHelper::values($asset, $columns, $categoryPath),
            ];
        });

        return Inertia::render('dynamic/assets/list', [
            ...$props,
            'tables' => null,
            'upsOptions' => [],
            'assets' => $assets,
            'groupedTable' => null,
        ]);
    }

    /**
     * Build the UPS and Battery datasets for a split sheet.
     *
     * @param  array<string, array{label: string, columns: list<array<string, mixed>>, shared: list<string>, identity: list<string>}>  $tables
     * @return array<string, mixed>
     */
    private function splitTables(Request $request, string $sheet, array $tables, int $perPage): array
    {
        if (isset($tables['device'], $tables['camera'])) {
            return $this->cctvTables($request, $sheet, $tables, $perPage);
        }

        $search = trim($request->string('search')->toString());
        $batteryCategoryIds = AssetSheetHelper::batteryCategoryIds($sheet);
        $upsCategoryIds = array_values(array_diff(
            AssetSheetHelper::categoryIds($sheet),
            $batteryCategoryIds,
        ));

        $nameById = Category::query()->pluck('name', 'id');
        $parentIdById = Category::query()->pluck('parent_id', 'id');

        $ups = $this->upsPaginator(
            $upsCategoryIds,
            $search,
            $perPage,
            $tables['ups']['columns'],
            $tables['ups']['identity'],
            $nameById,
            $parentIdById,
        );

        $battery = $this->batteryPaginator(
            $batteryCategoryIds,
            $upsCategoryIds,
            $search,
            $perPage,
            $tables['battery']['columns'],
            $nameById,
            $parentIdById,
        );

        return [
            'ups' => [
                'label' => $tables['ups']['label'],
                'columns' => $tables['ups']['columns'],
                'identity' => $tables['ups']['identity'],
                'shared' => $tables['ups']['shared'],
                'groups' => $ups,
            ],
            'battery' => [
                'label' => $tables['battery']['label'],
                'columns' => $tables['battery']['columns'],
                'shared' => $tables['battery']['shared'],
                'groups' => $battery,
            ],
        ];
    }

    /**
     * Build the CCTV device and camera tables. The device table lists one row
     * per DVR/NVR; the camera table groups camera channels by their parent
     * device and repeats the device columns once per group.
     *
     * @param  array<string, array{label: string, columns: list<array<string, mixed>>, shared: list<string>, identity: list<string>}>  $tables
     * @return array<string, mixed>
     */
    private function cctvTables(Request $request, string $sheet, array $tables, int $perPage): array
    {
        $search = trim($request->string('search')->toString());
        $cameraCategoryIds = AssetSheetHelper::cctvCameraCategoryIds();
        $deviceCategoryIds = array_values(array_diff(
            AssetSheetHelper::categoryIds('cctv'),
            $cameraCategoryIds,
        ));
        $nameById = Category::query()->pluck('name', 'id');
        $parentIdById = Category::query()->pluck('parent_id', 'id');

        return [
            'device' => [
                'label' => $tables['device']['label'],
                'columns' => $tables['device']['columns'],
                'identity' => $tables['device']['identity'],
                'groups' => $this->cctvDevicePaginator(
                    $request,
                    $sheet,
                    $tables['device']['columns'],
                    $deviceCategoryIds,
                    $cameraCategoryIds,
                    $perPage,
                    $nameById,
                    $parentIdById,
                ),
            ],
            'camera' => [
                'label' => $tables['camera']['label'],
                'columns' => $tables['camera']['columns'],
                'shared' => $tables['camera']['shared'],
                'hidden' => $tables['camera']['hidden'],
                'groups' => $this->cctvCameraPaginator(
                    $sheet,
                    $tables['camera']['columns'],
                    $tables['camera']['shared'],
                    $cameraCategoryIds,
                    $perPage,
                    $nameById,
                    $parentIdById,
                    $search,
                ),
            ],
        ];
    }

    /**
     * Paginated CCTV device groups. Devices are grouped by their physical
     * identity (name, category, brand, IP, serial, model) and collapsed into
     * one row; duplicate records merge their HDD capacities and camera count.
     *
     * @param  list<array<string, mixed>>  $columns
     * @param  list<int>  $deviceCategoryIds
     * @param  list<int>  $cameraCategoryIds
     * @param  Collection<int, string>  $nameById
     * @param  Collection<int, int|null>  $parentIdById
     */
    private function cctvDevicePaginator(
        Request $request,
        string $sheet,
        array $columns,
        array $deviceCategoryIds,
        array $cameraCategoryIds,
        int $perPage,
        Collection $nameById,
        Collection $parentIdById,
    ): LengthAwarePaginator {
        $query = Asset::query();
        AssetSheetHelper::filterBySheet($query, $sheet);
        AssetSheetHelper::search($query, $request->string('search')->toString());

        $rows = $query
            ->whereIn('category_id', $deviceCategoryIds)
            ->whereNull('parent_asset_id')
            ->withCount(['children as camera_count' => function (Builder $child) use ($cameraCategoryIds): void {
                $child->whereIn('category_id', $cameraCategoryIds);
            }])
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(function (Asset $asset) use ($columns, $nameById, $parentIdById): array {
                $categoryPath = AssetSheetHelper::categoryPath($asset, $nameById, $parentIdById);
                $values = AssetSheetHelper::values($asset, $columns, $categoryPath);
                $values['jumlah_kamera'] = (string) $asset->camera_count;

                return [
                    'id' => $asset->id,
                    'parent_asset_id' => $asset->parent_asset_id,
                    'category_path' => $categoryPath,
                    'managed_sheet' => null,
                    'device_key' => AssetSheetHelper::cctvDeviceIdentityKey($asset),
                    'in_checklist' => (bool) $asset->in_checklist,
                    'checklist_category_id' => $asset->checklist_category_id,
                    'values' => $values,
                ];
            });

        $groups = $rows
            ->groupBy('device_key')
            ->map(function (Collection $members, string $key): array {
                // Duplicate device records collapse into one row: the row of
                // the device that owns the checklist (or the first one).
                $primary = $members->first(fn (array $row): bool => $row['in_checklist']
                    && $row['checklist_category_id'] !== null) ?? $members->first();

                $values = [];

                foreach (array_keys($primary['values']) as $columnKey) {
                    if ($columnKey === 'kapasitas_hd') {
                        $values[$columnKey] = AssetSheetHelper::mergeCapacityValues(
                            $members->pluck('values.'.$columnKey),
                        );

                        continue;
                    }

                    if ($columnKey === 'jumlah_kamera') {
                        $values[$columnKey] = (string) $members->sum(
                            fn (array $row): int => (int) ($row['values']['jumlah_kamera'] ?? 0),
                        );

                        continue;
                    }

                    $values[$columnKey] = $members
                        ->map(fn (array $row): string => trim((string) ($row['values'][$columnKey] ?? '')))
                        ->first(fn (string $value): bool => $value !== '') ?? '';
                }

                $primary['values'] = $values;
                unset($primary['in_checklist'], $primary['checklist_category_id']);

                return [
                    'key' => $key,
                    'rows' => [$primary],
                ];
            })
            ->values();

        return $this->paginateGroups($groups, 'cctv_page', $perPage);
    }

    /**
     * Paginated CCTV camera groups. Cameras without a parent (legacy records)
     * group by their own name so they still render under one header.
     *
     * @param  list<array<string, mixed>>  $columns
     * @param  list<string>  $shared
     * @param  list<int>  $cameraCategoryIds
     * @param  Collection<int, string>  $nameById
     * @param  Collection<int, int|null>  $parentIdById
     */
    private function cctvCameraPaginator(
        string $sheet,
        array $columns,
        array $shared,
        array $cameraCategoryIds,
        int $perPage,
        Collection $nameById,
        Collection $parentIdById,
        string $search,
    ): LengthAwarePaginator {
        $query = Asset::query();
        AssetSheetHelper::filterBySheet($query, $sheet);
        $query->whereIn('category_id', $cameraCategoryIds);

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                AssetSheetHelper::search($query, $search);
                // The device name lives on the parent asset, so searching
                // "CCTV-1" should still find its cameras.
                $query->orWhereHas(
                    'parent',
                    fn (Builder $parent) => $parent->where('name', 'like', '%'.$search.'%'),
                );
            });
        }

        $rows = $query
            ->with(['parent:id,name,category_id,brand,ip_address,room,floor'])
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(function (Asset $camera) use ($columns, $shared, $nameById, $parentIdById): array {
                $categoryPath = AssetSheetHelper::categoryPath($camera, $nameById, $parentIdById);
                $device = $camera->parent;
                $sharedValues = array_intersect_key([
                    'perangkat' => $device?->name ?? '',
                    'kategori' => $device !== null
                        ? (string) $nameById->get($device->category_id)
                        : '',
                    'merek_perangkat' => $device?->brand ?? '',
                    'ip_perangkat' => $device?->ip_address ?? '',
                    'ruang' => $device?->room ?? '',
                    'lantai_perangkat' => $device?->floor ?? '',
                ], array_flip($shared));

                return [
                    'id' => $camera->id,
                    'parent_asset_id' => $camera->parent_asset_id,
                    'category_path' => $categoryPath,
                    'managed_sheet' => null,
                    'device_key' => $device !== null
                        ? 'parent:'.$device->id
                        : 'name:'.mb_strtolower(trim($camera->name)),
                    'device_shared' => $sharedValues,
                    'values' => AssetSheetHelper::values($camera, $columns, $categoryPath),
                ];
            });

        $groups = $rows
            ->groupBy('device_key')
            ->map(function (Collection $members, string $key): array {
                /** @var array{device_shared: array<string, string>, values: array<string, string>} $first */
                $first = $members->first();

                return [
                    'key' => $key,
                    'name' => $first['device_shared']['perangkat']
                        ?? trim((string) ($first['values']['nama'] ?? '')),
                    'shared' => $first['device_shared'],
                    'rows' => $members
                        ->map(function (array $row): array {
                            unset($row['device_key'], $row['device_shared']);

                            return $row;
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        return $this->paginateGroups($groups, 'camera_page', $perPage);
    }

    /**
     * Wrap grouped rows in a paginator with its own page name.
     *
     * @param  Collection<int, array<string, mixed>>  $groups
     */
    private function paginateGroups(Collection $groups, string $pageName, int $perPage): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage($pageName);

        return (new LengthAwarePaginator(
            $groups->forPage($page, $perPage)->values(),
            $groups->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        ))
            ->setPageName($pageName)
            ->withQueryString();
    }

    /**
     * Paginated groups for a sheet whose rows merge shared device columns.
     * Assets that share the same `group_by` values are grouped so the identity
     * columns render once with rowSpan; the rest stay per row. Merged cells
     * show the first non-empty value found in the group.
     *
     * @param  list<string>  $identity
     * @param  list<string>  $groupBy
     * @param  list<array<string, mixed>>  $columns
     */
    private function groupedPaginator(
        Request $request,
        string $sheet,
        array $identity,
        array $groupBy,
        array $columns,
        int $perPage,
    ): LengthAwarePaginator {
        $query = Asset::query();
        AssetSheetHelper::filterBySheet($query, $sheet);
        AssetSheetHelper::search($query, $request->string('search')->toString());

        $nameById = Category::query()->pluck('name', 'id');
        $parentIdById = Category::query()->pluck('parent_id', 'id');
        $cameraCategoryIds = $sheet === 'cctv' ? AssetSheetHelper::cctvCameraCategoryIds() : [];

        $rows = $query
            ->with('parent:id,name,category_id')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(function (Asset $asset) use ($columns, $nameById, $parentIdById, $sheet, $cameraCategoryIds): array {
                $categoryPath = AssetSheetHelper::categoryPath($asset, $nameById, $parentIdById);

                return [
                    'id' => $asset->id,
                    'parent_asset_id' => $asset->parent_asset_id,
                    'category_path' => $categoryPath,
                    'managed_sheet' => null,
                    'device_key' => $sheet === 'cctv'
                        ? AssetSheetHelper::cctvDeviceKey($asset, $cameraCategoryIds)
                        : null,
                    'values' => AssetSheetHelper::values($asset, $columns, $categoryPath),
                ];
            });

        $groups = $rows
            ->groupBy(fn (array $row): string => $row['device_key'] ?? collect($groupBy)
                ->map(fn (string $key): string => mb_strtolower(trim((string) ($row['values'][$key] ?? ''))))
                ->implode("\u{1F}"))
            ->map(function (Collection $members, string $key) use ($identity): array {
                // The device leads the group so the merged identity cells are
                // read from it, not from one of its camera channels.
                $members = $members
                    ->sortBy(fn (array $row): int => ($row['parent_asset_id'] ?? null) === null ? 0 : 1)
                    ->values();
                /** @var array{values: array<string, string>} $first */
                $first = $members->first();

                foreach ($identity as $identityKey) {
                    $first['values'][$identityKey] = $members
                        ->map(fn (array $row): string => trim((string) ($row['values'][$identityKey] ?? '')))
                        ->first(fn (string $value): bool => $value !== '') ?? '';
                }

                $members->put(0, $first);

                return [
                    'key' => $key,
                    'rows' => $members->all(),
                ];
            })
            ->values();

        $pageName = str_replace('-', '_', $sheet).'_page';
        $page = LengthAwarePaginator::resolveCurrentPage($pageName);

        return (new LengthAwarePaginator(
            $groups->forPage($page, $perPage)->values(),
            $groups->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        ))
            ->setPageName($pageName)
            ->withQueryString();
    }

    /**
     * Paginated UPS groups for the split sheet. Assets that share the same
     * identity values (name, brand, capacity) are grouped so those columns
     * render once with rowSpan; the frontend sub-groups each UPS by its frame
     * columns.
     *
     * @param  list<int>  $categoryIds
     * @param  list<array<string, mixed>>  $columns
     * @param  list<string>  $identity
     * @param  Collection<int, string>  $nameById
     * @param  Collection<int, int|null>  $parentIdById
     */
    private function upsPaginator(
        array $categoryIds,
        string $search,
        int $perPage,
        array $columns,
        array $identity,
        Collection $nameById,
        Collection $parentIdById,
    ): LengthAwarePaginator {
        $query = Asset::query()->whereIn('category_id', $categoryIds);
        AssetSheetHelper::search($query, $search);

        $rows = $query
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(function (Asset $asset) use ($columns, $nameById, $parentIdById): array {
                $categoryPath = AssetSheetHelper::categoryPath($asset, $nameById, $parentIdById);

                return [
                    'id' => $asset->id,
                    'parent_asset_id' => $asset->parent_asset_id,
                    'category_path' => $categoryPath,
                    'managed_sheet' => null,
                    'values' => AssetSheetHelper::values($asset, $columns, $categoryPath),
                ];
            });

        $groups = $rows
            ->groupBy(fn (array $row): string => collect($identity)
                ->map(fn (string $key): string => (string) ($row['values'][$key] ?? ''))
                ->implode("\u{1F}"))
            ->map(fn (Collection $members, string $key): array => [
                'key' => $key,
                'rows' => $members->values()->all(),
            ])
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage('ups_page');

        return (new LengthAwarePaginator(
            $groups->forPage($page, $perPage)->values(),
            $groups->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        ))
            ->setPageName('ups_page')
            ->withQueryString();
    }

    /**
     * Battery assets grouped by their parent UPS. Each group is paginated as
     * one row set so a UPS with two battery banks keeps its shared columns.
     *
     * @param  list<int>  $batteryCategoryIds
     * @param  list<int>  $upsCategoryIds
     * @param  list<array<string, mixed>>  $columns
     * @param  Collection<int, string>  $nameById
     * @param  Collection<int, int|null>  $parentIdById
     */
    private function batteryPaginator(
        array $batteryCategoryIds,
        array $upsCategoryIds,
        string $search,
        int $perPage,
        array $columns,
        Collection $nameById,
        Collection $parentIdById,
    ): LengthAwarePaginator {
        $upsByName = Asset::query()
            ->whereIn('category_id', $upsCategoryIds)
            ->orderBy('id')
            ->get()
            ->keyBy(fn (Asset $asset): string => mb_strtolower(trim($asset->name)));

        $groups = Asset::query()
            ->whereIn('category_id', $batteryCategoryIds)
            ->with('parent')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Asset $asset): string => $asset->parent_asset_id !== null
                ? 'parent:'.$asset->parent_asset_id
                : 'name:'.mb_strtolower(trim($asset->name)))
            ->map(function (Collection $members) use ($upsByName, $columns, $nameById, $parentIdById): array {
                /** @var Asset $first */
                $first = $members->first();
                $parent = $first->parent ?? $upsByName->get(mb_strtolower(trim($first->name)));

                return [
                    'key' => $parent !== null
                        ? 'parent:'.$parent->id
                        : 'name:'.mb_strtolower(trim($first->name)),
                    'name' => $parent?->name ?? $first->name,
                    'brand' => $parent?->brand ?? $first->brand,
                    'capacity' => $parent?->capacity ?? $first->capacity,
                    'rows' => $members
                        ->map(function (Asset $battery) use ($columns, $nameById, $parentIdById): array {
                            $categoryPath = AssetSheetHelper::categoryPath($battery, $nameById, $parentIdById);

                            return [
                                'id' => $battery->id,
                                'parent_asset_id' => $battery->parent_asset_id,
                                'category_path' => $categoryPath,
                                'managed_sheet' => null,
                                'values' => AssetSheetHelper::values($battery, $columns, $categoryPath),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $group): bool => $this->groupMatchesSearch($group, $search))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage('battery_page');

        return (new LengthAwarePaginator(
            $groups->forPage($page, $perPage)->values(),
            $groups->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        ))
            ->setPageName('battery_page')
            ->withQueryString();
    }

    /**
     * Whether a battery group matches the free-text search. The shared
     * columns come from the parent UPS, so they are searched too.
     *
     * @param  array<string, mixed>  $group
     */
    private function groupMatchesSearch(array $group, string $search): bool
    {
        if ($search === '') {
            return true;
        }

        $haystacks = [$group['name'], $group['brand'], $group['capacity']];

        foreach ($group['rows'] as $row) {
            foreach ($row['values'] as $value) {
                $haystacks[] = $value;
            }
        }

        foreach ($haystacks as $value) {
            if (mb_stripos((string) $value, $search) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parent assets selectable in the create/edit form. For UPS & Battery
     * these are the UPS units, deduplicated by identity (name, brand,
     * capacity) because every module row of the same UPS is its own asset;
     * for CCTV they are the DVR/NVR devices selectable as a camera parent.
     *
     * @return list<array{id: int, name: string, brand: string|null, capacity: string|null, category?: string|null}>
     */
    private function upsOptions(string $sheet): array
    {
        if ($sheet === 'cctv') {
            // The camera form picks its parent device from this list.
            $cameraCategoryIds = AssetSheetHelper::cctvCameraCategoryIds();
            $deviceCategoryIds = array_values(array_diff(
                AssetSheetHelper::categoryIds($sheet),
                $cameraCategoryIds,
            ));

            return Asset::query()
                ->with('category:id,name')
                ->whereIn('category_id', $deviceCategoryIds)
                ->whereNull('parent_asset_id')
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name', 'brand', 'capacity', 'category_id'])
                ->map(fn (Asset $asset): array => [
                    'id' => $asset->id,
                    'name' => $asset->name,
                    'brand' => $asset->brand,
                    'capacity' => $asset->capacity,
                    'category' => $asset->category?->name,
                ])
                ->values()
                ->all();
        }

        $batteryCategoryIds = AssetSheetHelper::batteryCategoryIds($sheet);
        $upsCategoryIds = array_values(array_diff(
            AssetSheetHelper::categoryIds($sheet),
            $batteryCategoryIds,
        ));

        return Asset::query()
            ->whereIn('category_id', $upsCategoryIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'brand', 'capacity'])
            ->unique(fn (Asset $asset): string => implode('|', [
                mb_strtolower(trim($asset->name)),
                mb_strtolower(trim((string) $asset->brand)),
                trim((string) $asset->capacity),
            ]))
            ->map(fn (Asset $asset): array => [
                'id' => $asset->id,
                'name' => $asset->name,
                'brand' => $asset->brand,
                'capacity' => $asset->capacity,
            ])
            ->values()
            ->all();
    }

    /**
     * Existing assets grouped by name for the create/edit form. Selecting a
     * name fills brand/capacity, and selecting a frame number fills its
     * year/SN. Sheets with autofill columns (row_group identity or an explicit
     * `autofill` list) also receive those values (the first non-empty one
     * across the group) so picking an existing device fills its fields at once.
     *
     * @return list<array{name: string, brand: string|null, capacity: string|null, frames: list<array{nomor: string, tahun: string, sn: string}>, values?: array<string, string>, category_path?: list<string>}>
     */
    private function assetPresets(string $sheet): array
    {
        $query = Asset::query();
        $categoryIds = AssetSheetHelper::categoryIds($sheet);

        if ($categoryIds !== []) {
            $query->whereIn('category_id', $categoryIds);
        }

        // Camera channels are part of a device, not devices themselves, so
        // they must not show up as create-form presets.
        if ($sheet === 'cctv') {
            $query->whereNull('parent_asset_id');
        }

        $columns = AssetSheetHelper::columns($sheet);
        $multiRowKeys = AssetSheetHelper::multiRow($sheet)['keys'] ?? [];
        $skippedKeys = collect($columns)
            ->filter(fn (array $column): bool => ($column['computed'] ?? false)
                || in_array($column['storage'], ['name', 'notes'], true)
                || in_array($column['key'], $multiRowKeys, true))
            ->pluck('key')
            ->all();
        /*
        | Only device-level (identity/autofill) columns are copied. Per-row
        | columns such as Kapasitas HD are left blank because they may differ
        | between the cameras of one device.
        */
        $autofillKeys = array_values(array_diff(
            AssetSheetHelper::autofill($sheet),
            $skippedKeys,
        ));
        $nameById = Category::query()->pluck('name', 'id');
        $parentIdById = Category::query()->pluck('parent_id', 'id');

        return $query
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Asset $asset): string => mb_strtolower(trim($asset->name)))
            ->map(function (Collection $members) use ($columns, $autofillKeys, $nameById, $parentIdById): array {
                /** @var Asset $first */
                $first = $members->first();

                $frames = $members
                    ->map(fn (Asset $asset): array => [
                        'nomor' => (string) ($asset->details['frame_nomor'] ?? ''),
                        'tahun' => (string) ($asset->details['frame_tahun'] ?? ''),
                        'sn' => (string) ($asset->details['frame_sn'] ?? ''),
                    ])
                    ->filter(fn (array $frame): bool => $frame['nomor'] !== '')
                    ->unique(fn (array $frame): string => $frame['nomor'])
                    ->values()
                    ->all();

                $preset = [
                    'name' => $first->name,
                    'brand' => $members->pluck('brand')->filter()->first(),
                    'capacity' => $members->pluck('capacity')->filter()->first(),
                    'frames' => $frames,
                ];

                if ($autofillKeys !== []) {
                    $categoryPath = AssetSheetHelper::categoryPath($first, $nameById, $parentIdById);
                    $memberValues = $members
                        ->map(fn (Asset $asset): array => AssetSheetHelper::values($asset, $columns, $categoryPath))
                        ->values();

                    $preset['values'] = collect($autofillKeys)
                        ->mapWithKeys(fn (string $key): array => [
                            $key => $memberValues
                                ->map(fn (array $values): string => trim((string) ($values[$key] ?? '')))
                                ->first(fn (string $value): bool => $value !== '') ?? '',
                        ])
                        ->all();
                    $preset['category_path'] = $categoryPath;
                }

                return $preset;
            })
            ->values()
            ->all();
    }
}
