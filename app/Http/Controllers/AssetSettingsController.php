<?php

namespace App\Http\Controllers;

use App\Events\AssetsChanged;
use App\Helpers\AssetSheetHelper;
use App\Models\Asset;
use App\Models\ChecklistCategory;
use App\Models\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AssetSettingsController extends Controller
{
    /**
     * Whitelisted text-search columns: request value => database column.
     * Every option matches a column shown on the settings table.
     *
     * @var array<string, string>
     */
    private const SEARCH_COLUMNS = [
        'name' => 'name',
        'brand' => 'brand',
        'model' => 'model',
        'serial_number' => 'serial_number',
    ];

    /**
     * Show the asset settings page (/assets/settings): the grouped asset list
     * with the barcode scan flags (checklist, maintenance).
     */
    public function index(Request $request): InertiaResponse
    {
        $canManage = $request->user()->hasPermissionTo('manage asset management');

        $childMenu = Menu::where('url', '/'.$request->path())->first();

        return Inertia::render('dynamic/assets/settings', [
            ...$this->childMenuProps($childMenu?->parent, $childMenu),
            'assets' => $this->groupedAssets($request),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'search_column' => $this->resolveSearchColumn($request),
                'per_page' => $request->integer('per_page', 10),
            ],
            'canManage' => $canManage,
            'checklistCategories' => ChecklistCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn (ChecklistCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])
                ->values(),
        ]);
    }

    /**
     * Update an asset's checklist category and maintenance flag. Each control
     * saves on change, so the request carries only what the admin touched.
     */
    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $validated = $request->validate([
            'in_checklist' => ['sometimes', 'boolean'],
            'in_maintenance' => ['sometimes', 'boolean'],
            'checklist_category_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('checklist_categories', 'id')->where('is_active', true),
            ],
        ]);

        // Picking a checklist category implies the asset belongs to the
        // checklist. Sending null keeps the current flag so the UI can show
        // the "belum dipilih" state without removing the asset.
        if (($validated['checklist_category_id'] ?? null) !== null) {
            $validated['in_checklist'] = true;
        }

        $asset->update($validated);

        AssetsChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Asset setting updated.')]);

        return back();
    }

    /**
     * Apply checklist/maintenance changes to every asset in a grouped row.
     * UPS assets that share a name are shown as one row, so a single change
     * must reach all of its members.
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'asset_ids' => ['required', 'array', 'min:1'],
            'asset_ids.*' => ['integer', Rule::exists('assets', 'id')],
            'in_checklist' => ['sometimes', 'boolean'],
            'in_maintenance' => ['sometimes', 'boolean'],
            'checklist_category_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('checklist_categories', 'id')->where('is_active', true),
            ],
        ]);

        $assetIds = $validated['asset_ids'];
        unset($validated['asset_ids']);

        // Picking a checklist category implies the asset belongs to the
        // checklist. Sending null keeps the current flag so the UI can show
        // the "belum dipilih" state without removing the asset.
        if (($validated['checklist_category_id'] ?? null) !== null) {
            $validated['in_checklist'] = true;
        }

        // Update each model so the checklist observer materialises the
        // generated items for every member of the group. Camera channels are
        // part of the device and never own checklist items, so they are
        // skipped even if the client sends their ids.
        if ($validated !== []) {
            $cameraCategoryIds = AssetSheetHelper::cctvCameraCategoryIds();

            Asset::query()
                ->whereIn('id', $assetIds)
                ->get()
                ->reject(fn (Asset $asset): bool => AssetSheetHelper::isCctvCamera($asset, $cameraCategoryIds))
                ->each(fn (Asset $asset) => $asset->update($validated));
        }

        AssetsChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Asset setting updated.')]);

        return back();
    }

    /**
     * Build the settings rows. UPS assets that share a name are collapsed
     * into one row; every other asset keeps its own row. Search and
     * pagination run on the grouped rows.
     */
    private function groupedAssets(Request $request): LengthAwarePaginator
    {
        $search = trim($request->string('search')->toString());
        $column = $this->resolveSearchColumn($request);
        $perPage = max(1, $request->integer('per_page', 10));
        $page = LengthAwarePaginator::resolveCurrentPage();

        $upsCategoryIds = AssetSheetHelper::categoryIds('ups-batt');
        $cctvCategoryIds = AssetSheetHelper::categoryIds('cctv');
        $cameraCategoryIds = AssetSheetHelper::cctvCameraCategoryIds();

        $groups = Asset::query()
            ->with(['category:id,name', 'parent:id,name,category_id'])
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Asset $asset): string => match (true) {
                in_array($asset->category_id, $upsCategoryIds, true) => 'ups:'.mb_strtolower(trim($asset->name)),
                in_array($asset->category_id, $cctvCategoryIds, true) => AssetSheetHelper::cctvDeviceKey($asset, $cameraCategoryIds),
                default => 'asset:'.$asset->id,
            })
            ->filter(fn (Collection $members): bool => $this->membersMatchSearch($members, $search, $column))
            ->map(fn (Collection $members): array => $this->settingsRow($members, $cameraCategoryIds))
            ->values();

        return (new LengthAwarePaginator(
            $groups->forPage($page, $perPage)->values(),
            $groups->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        ))->withQueryString();
    }

    /**
     * Shape one settings row from a group of assets. The device drives the
     * displayed values; the members power the group QR dialog.
     *
     * @param  Collection<int, Asset>  $members
     * @param  list<int>  $cameraCategoryIds
     * @return array<string, mixed>
     */
    private function settingsRow(Collection $members, array $cameraCategoryIds): array
    {
        // A CCTV device must lead its group so the identity columns, QR, and
        // checklist flag come from the device and not from a camera channel.
        $members = $members
            ->sortBy(fn (Asset $asset): int => $asset->parent_asset_id === null ? 0 : 1)
            ->values();

        $first = $members->first();

        return [
            'id' => $first->id,
            'asset_ids' => $members
                ->reject(fn (Asset $asset): bool => AssetSheetHelper::isCctvCamera($asset, $cameraCategoryIds))
                ->pluck('id')
                ->values()
                ->all(),
            'name' => AssetSheetHelper::displayName($first),
            'category' => $first->category?->name,
            'brand' => $first->brand,
            'model' => $first->model,
            'serial_number' => $first->serial_number,
            'capacity' => $first->capacity,
            'manufacture_year' => $first->manufacture_year,
            'operation_year' => $first->operation_year,
            'quantity' => $first->quantity,
            'floor' => $first->floor,
            'room' => $first->room,
            'in_checklist' => (bool) $first->in_checklist,
            'in_maintenance' => (bool) $first->in_maintenance,
            'checklist_category_id' => $first->checklist_category_id,
            'scan_group' => AssetSheetHelper::isCctvDeviceCategory(
                (int) $first->category_id,
                $cameraCategoryIds,
            )
                ? [
                    'name' => $first->name,
                    'category_id' => $first->category_id,
                ]
                : null,
            'members' => $members
                ->map(fn (Asset $asset): array => [
                    'id' => $asset->id,
                    'name' => AssetSheetHelper::displayName($asset),
                    'category' => $asset->category?->name,
                    'serial_number' => $asset->serial_number,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Whether any member of the group matches the settings search.
     *
     * @param  Collection<int, Asset>  $members
     */
    private function membersMatchSearch(Collection $members, string $search, string $column): bool
    {
        if ($search === '') {
            return true;
        }

        $columns = $column === 'all'
            ? array_values(self::SEARCH_COLUMNS)
            : [self::SEARCH_COLUMNS[$column]];

        foreach ($members as $member) {
            if (($column === 'all' || $column === 'name')
                && mb_stripos(AssetSheetHelper::displayName($member), $search) !== false) {
                return true;
            }

            foreach ($columns as $key) {
                if (mb_stripos((string) $member->{$key}, $search) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Resolve the requested search column against the whitelist.
     */
    private function resolveSearchColumn(Request $request): string
    {
        $column = $request->string('search_column')->toString();

        if ($column === '' || $column === 'all') {
            return 'all';
        }

        return isset(self::SEARCH_COLUMNS[$column]) ? $column : 'all';
    }
}
