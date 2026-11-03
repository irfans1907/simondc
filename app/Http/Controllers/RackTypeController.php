<?php

namespace App\Http\Controllers;

use App\Http\Requests\RackTypeRequest;
use App\Models\Menu;
use App\Models\Rack;
use App\Models\RackType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RackTypeController extends Controller
{
    /**
     * Whitelisted search columns: request value => database column.
     *
     * @var array<string, string>
     */
    private const SEARCH_COLUMNS = [
        'name' => 'name',
        'capacity' => 'capacity',
    ];

    /**
     * Show the rack list page (/rack/racklist lists the rack types).
     */
    public function index(Request $request): InertiaResponse
    {
        $perPage = $request->integer('per_page', 10);

        $canManage = $request->user()->hasPermissionTo('manage rack management');

        $childMenu = Menu::where('url', '/'.$request->path())->first();

        return Inertia::render('dynamic/rack/racklist', [
            ...$this->childMenuProps($childMenu?->parent, $childMenu),
            'rackTypes' => $this->filteredQuery($request)
                ->withCount('racks')
                ->paginate($perPage)
                ->withQueryString(),
            'canManage' => $canManage,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'search_column' => $this->resolveSearchColumn($request),
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created rack type.
     */
    public function store(RackTypeRequest $request): RedirectResponse
    {
        RackType::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rack type created.')]);

        return to_route('rack.racklist');
    }

    /**
     * Update the given rack type, cascading the new name and capacity to the
     * racks that use it.
     */
    public function update(RackTypeRequest $request, RackType $rackType): RedirectResponse
    {
        $data = $request->validated();

        // Update the affected racks one by one: a mass query builder update
        // would bypass the activity log, leaving the cascade unrecorded.
        $racks = Rack::query()
            ->where('type', $rackType->name)
            ->get();

        foreach ($racks as $rack) {
            $rack->update([
                'type' => $data['name'],
                'capacity' => $data['capacity'],
            ]);
        }

        $rackType->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rack type updated.')]);

        return to_route('rack.racklist');
    }

    /**
     * Delete the given rack type when no rack uses it.
     */
    public function destroy(RackType $rackType): RedirectResponse
    {
        $rackCount = $rackType->racks()->count();

        if ($rackCount > 0) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Rack type is still used by :count rack(s).', ['count' => $rackCount]),
            ]);

            return to_route('rack.racklist');
        }

        $rackType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rack type deleted.')]);

        return to_route('rack.racklist');
    }

    /**
     * Build the rack type query filtered by the request search parameters.
     */
    private function filteredQuery(Request $request): Builder
    {
        $search = $request->string('search')->toString();

        return RackType::query()
            ->when($search !== '', function (Builder $query) use ($search, $request) {
                $column = $this->resolveSearchColumn($request);

                if ($column === 'all') {
                    $query->where(function (Builder $query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('capacity', 'like', "%{$search}%");
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
