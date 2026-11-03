<?php

namespace App\Http\Controllers;

use App\Events\RacksChanged;
use App\Http\Requests\RackRequest;
use App\Models\Menu;
use App\Models\Rack;
use App\Models\RackType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RackController extends Controller
{
    /**
     * Whitelisted search columns: request value => database column.
     * "customer_name" lives on the related customer, so filteredQuery()
     * resolves it through whereHas() instead of a direct column.
     *
     * @var array<string, string>
     */
    private const SEARCH_COLUMNS = [
        'rack_code' => 'rack_code',
        'floor' => 'floor',
        'room' => 'room',
        'status' => 'status',
        'customer_name' => 'customer_name',
        'brand' => 'brand',
        'type' => 'type',
        'capacity' => 'capacity',
        'dimension' => 'dimension',
    ];

    /**
     * Redirect the module root to the rack overview page.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('rack.overview');
    }

    /**
     * Show the rack overview page (/rack/overview renders the rack list).
     */
    public function overview(Request $request): InertiaResponse
    {
        return $this->renderIndex($request, 'dynamic/rack/overview');
    }

    /**
     * Render the rack list under the given Inertia component.
     */
    private function renderIndex(Request $request, string $component): InertiaResponse
    {
        $perPage = $request->integer('per_page', 10);

        $canManage = $request->user()->hasPermissionTo('manage rack management');

        $childMenu = Menu::where('url', '/'.$request->path())->first();

        return Inertia::render($component, [
            ...$this->childMenuProps($childMenu?->parent, $childMenu),
            'racks' => $this->filteredQuery($request)
                ->paginate($perPage)
                ->withQueryString(),
            'rackTypes' => RackType::query()->orderBy('name')->get(['name', 'capacity']),
            'canManage' => $canManage,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'search_column' => $this->resolveSearchColumn($request),
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created rack.
     */
    public function store(RackRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['capacity'] = RackType::query()->where('name', $data['type'])->value('capacity');
        $data['status'] = Rack::DEFAULT_STATUS;

        Rack::create($data);

        RacksChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rack created.')]);

        return to_route('rack.index');
    }

    /**
     * Update the given rack.
     */
    public function update(RackRequest $request, Rack $rack): RedirectResponse
    {
        $data = $request->validated();

        $data['capacity'] = RackType::query()->where('name', $data['type'])->value('capacity');

        $rack->update($data);

        RacksChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rack updated.')]);

        return to_route('rack.index');
    }

    /**
     * Delete the given rack, unless a customer still occupies it.
     */
    public function destroy(Rack $rack): RedirectResponse
    {
        $customerCount = $rack->customers()->count();

        if ($customerCount > 0) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Rack is still assigned to :count customer(s).', ['count' => $customerCount]),
            ]);

            return to_route('rack.index');
        }

        $rack->delete();

        RacksChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rack deleted.')]);

        return to_route('rack.index');
    }

    /**
     * Export the filtered racks as a CSV file.
     */
    public function export(Request $request): StreamedResponse
    {
        $racks = $this->filteredQuery($request)->get();

        $headers = [
            'No', 'Rack ID', 'Lantai', 'Ruang', 'Status', 'Nama Customer', 'Merek', 'Tipe', 'Kapasitas',
            'Dimensi', 'PDU Outlet 1', 'PDU Outlet 2', 'Tahun Manufaktur', 'Tahun Operasi', 'Catatan',
        ];

        return Response::streamDownload(function () use ($racks, $headers) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, escape: '\\');

            $row = 0;

            foreach ($racks as $rack) {
                fputcsv($handle, [
                    ++$row,
                    $rack->rack_code,
                    $rack->floor,
                    $rack->room,
                    $rack->status,
                    $rack->customers->first()?->customer_name ?? '-',
                    $rack->brand,
                    $rack->type,
                    $rack->capacity,
                    $rack->dimension,
                    $rack->pdu_outlet_1,
                    $rack->pdu_outlet_2,
                    $rack->manufacture_year,
                    $rack->operation_year,
                    $rack->notes,
                ], escape: '\\');
            }

            fclose($handle);
        }, 'racks-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Build the rack query filtered by the request search parameters.
     */
    private function filteredQuery(Request $request): Builder
    {
        $search = $request->string('search')->toString();

        return Rack::query()
            ->with('customers:id,rack_id,customer_name')
            ->when($search !== '', function (Builder $query) use ($search, $request) {
                $column = $this->resolveSearchColumn($request);

                if ($column === 'customer_name') {
                    $query->whereHas('customers', fn (Builder $customer) => $customer->where('customer_name', 'like', "%{$search}%"));

                    return;
                }

                if ($column === 'all') {
                    $query->where(function (Builder $query) use ($search) {
                        $query->where('rack_code', 'like', "%{$search}%")
                            ->orWhere('floor', 'like', "%{$search}%")
                            ->orWhere('room', 'like', "%{$search}%")
                            ->orWhere('status', 'like', "%{$search}%")
                            ->orWhere('brand', 'like', "%{$search}%")
                            ->orWhere('type', 'like', "%{$search}%")
                            ->orWhere('capacity', 'like', "%{$search}%")
                            ->orWhere('dimension', 'like', "%{$search}%")
                            ->orWhere('manufacture_year', 'like', "%{$search}%")
                            ->orWhere('operation_year', 'like', "%{$search}%")
                            ->orWhereHas('customers', fn (Builder $customer) => $customer->where('customer_name', 'like', "%{$search}%"));
                    });

                    return;
                }

                $query->where(self::SEARCH_COLUMNS[$column], 'like', "%{$search}%");
            })
            ->orderBy('rack_code');
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
