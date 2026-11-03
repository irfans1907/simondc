<?php

namespace App\Http\Controllers;

use App\Events\AssetsChanged;
use App\Models\Asset;
use App\Models\Menu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AssetSettingsController extends Controller
{
    /**
     * Whitelisted text-search columns: request value => database column.
     * Every option matches a column shown on the settings table (the
     * composed description is built from these same columns).
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
     * Show the asset settings page (/assets/settings): the per-asset list
     * with the barcode scan flags (checklist, maintenance) plus the device
     * description composed from the asset's own attributes.
     */
    public function index(Request $request): InertiaResponse
    {
        $canManage = $request->user()->hasPermissionTo('manage asset management');

        $childMenu = Menu::where('url', '/'.$request->path())->first();

        return Inertia::render('dynamic/assets/settings', [
            ...$this->childMenuProps($childMenu?->parent, $childMenu),
            'assets' => $this->filteredQuery($request)
                ->paginate($request->integer('per_page', 10))
                ->withQueryString(),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'search_column' => $this->resolveSearchColumn($request),
                'per_page' => $request->integer('per_page', 10),
            ],
            'canManage' => $canManage,
        ]);
    }

    /**
     * Update an asset's scan flags. The radios save per change, so the
     * request carries only the flag that was toggled.
     */
    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $validated = $request->validate([
            'in_checklist' => ['sometimes', 'boolean'],
            'in_maintenance' => ['sometimes', 'boolean'],
        ]);

        $asset->update($validated);

        AssetsChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Asset setting updated.')]);

        return back();
    }

    /**
     * Apply the settings search: "all" spans the description columns,
     * any other value hits its whitelisted column. Rows stay alphabetical.
     */
    private function filteredQuery(Request $request): Builder
    {
        $search = $request->string('search')->toString();

        return Asset::query()
            ->when($search !== '', function (Builder $query) use ($search, $request) {
                $column = $this->resolveSearchColumn($request);

                if ($column === 'all') {
                    $query->where(function (Builder $query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('brand', 'like', "%{$search}%")
                            ->orWhere('model', 'like', "%{$search}%")
                            ->orWhere('serial_number', 'like', "%{$search}%");
                    });

                    return;
                }

                $query->where(self::SEARCH_COLUMNS[$column], 'like', "%{$search}%");
            })
            ->orderBy('name');
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
