<?php

namespace App\Http\Controllers;

use App\Events\CustomersChanged;
use App\Http\Requests\CustomerRequest;
use App\Models\Asset;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\PanelMcb;
use App\Models\Rack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    /**
     * Whitelisted search columns: request value => database column. The
     * rack_code column lives on the related rack, so it is handled separately
     * in filteredQuery().
     *
     * @var array<string, string>
     */
    private const SEARCH_COLUMNS = [
        'customer_name' => 'customer_name',
        'city' => 'city',
        'location' => 'location',
        'service_status' => 'service_status',
    ];

    /**
     * Redirect the module root to the customer overview page.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('customer.overview');
    }

    /**
     * Show the customer overview page (/customer/overview lists customers).
     */
    public function overview(Request $request): InertiaResponse
    {
        $perPage = $request->integer('per_page', 10);

        $canManage = $request->user()->hasPermissionTo('manage customer list');

        $childMenu = Menu::where('url', '/'.$request->path())->first();

        return Inertia::render('dynamic/customer/overview', [
            ...$this->childMenuProps($childMenu?->parent, $childMenu),
            'customers' => $this->filteredQuery($request)
                ->paginate($perPage)
                ->withQueryString(),
            'availableRacks' => Rack::query()
                ->whereDoesntHave('customers')
                ->orderBy('rack_code')
                ->get(['id', 'rack_code', 'type', 'capacity', 'pdu_outlet_1', 'pdu_outlet_2']),
            'switchOptions' => $this->switchOptions(),
            'panelOptions' => $this->panelOptions(),
            'filterOptions' => [
                'cities' => Customer::CITIES,
                'locations' => Customer::LOCATIONS,
                'businessStatuses' => Customer::BUSINESS_STATUSES,
                'serviceStatuses' => Customer::SERVICE_STATUSES,
                'powerSources' => Customer::POWER_SOURCES,
            ],
            'canManage' => $canManage,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'search_column' => $this->resolveSearchColumn($request),
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Export the filtered customers as a CSV file. One row per customer;
     * the panel and MCB pairs are joined with " + ".
     */
    public function export(Request $request): StreamedResponse
    {
        $customers = $this->filteredQuery($request)->get();

        $headers = [
            'No', 'Kota', 'Lokasi', 'Nama Customer', 'Tipe Rak', 'Jenis Layanan', 'Sales Chanel',
            'Status Bisnis', 'Status Layanan', 'No Order', 'SID', 'Tanggal Aktivasi', 'Power Source',
            'Panel PDU', 'Nomor MCB', 'Connectivity', 'IP', 'Switch', 'Port Number', 'VLAN',
            'Layanan VLAN', 'Nama AM / Sales', 'Catatan',
        ];

        return Response::streamDownload(function () use ($customers, $headers) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, escape: '\\');

            $row = 0;

            foreach ($customers as $customer) {
                fputcsv($handle, [
                    ++$row,
                    $customer->city ?? '-',
                    $customer->location ?? '-',
                    $customer->customer_name,
                    $customer->rack?->type ?? '-',
                    $customer->service_type ?? '-',
                    $customer->sales_channel ?? '-',
                    $customer->business_status ?? '-',
                    $customer->service_status ?? '-',
                    $customer->order_number ?? '-',
                    $customer->sid_rack ?? '-',
                    $customer->activation_date ?? '-',
                    $customer->power_source ?? '-',
                    collect([$customer->panelAsset?->name, $customer->panelAsset2?->name])
                        ->filter()->join(' + ') ?: '-',
                    collect([$customer->panelMcb?->mcb_number, $customer->panelMcb2?->mcb_number])
                        ->filter()->join(' + ') ?: '-',
                    $customer->connectivity ?? '-',
                    $customer->ip_address ?? '-',
                    $customer->switch_type ?? '-',
                    $customer->port ?? '-',
                    $customer->vlan ?? '-',
                    $customer->service_vlan ?? '-',
                    $customer->account_manager ?? '-',
                    $customer->notes ?? '-',
                ], escape: '\\');
            }

            fclose($handle);
        }, 'customers-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Store a newly created customer and mark its rack as occupied.
     */
    public function store(CustomerRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Customer::create($data);

        $this->setRackStatus((int) $data['rack_id'], Rack::OCCUPIED_STATUS);

        CustomersChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer created.')]);

        return to_route('customer.index');
    }

    /**
     * Update the given customer. Moving to another rack frees the old rack
     * and occupies the new one.
     */
    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $previousRackId = $customer->rack_id;

        $data = $request->validated();

        $customer->update($data);

        if ($previousRackId !== (int) $data['rack_id']) {
            $this->setRackStatus($previousRackId, Rack::DEFAULT_STATUS, onlyWhenFree: true);
            $this->setRackStatus((int) $data['rack_id'], Rack::OCCUPIED_STATUS);
        }

        CustomersChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer updated.')]);

        return to_route('customer.index');
    }

    /**
     * Delete the given customer, freeing its rack for another customer.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $rackId = $customer->rack_id;

        $customer->delete();

        $this->setRackStatus($rackId, Rack::DEFAULT_STATUS, onlyWhenFree: true);

        CustomersChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer deleted.')]);

        return to_route('customer.index');
    }

    /**
     * Set a rack status through the model instead of a query builder so the
     * change is picked up by the activity log, and skip it when the rack
     * already carries the status so no empty log entry is written.
     *
     * @param  bool  $onlyWhenFree  only free a rack that no customer is
     *                              assigned to anymore
     */
    private function setRackStatus(int $rackId, string $status, bool $onlyWhenFree = false): void
    {
        $rack = Rack::query()->find($rackId);

        if ($rack === null || $rack->status === $status) {
            return;
        }

        if ($onlyWhenFree && $rack->customers()->exists()) {
            return;
        }

        $rack->update(['status' => $status]);
    }

    /**
     * Distinct asset NAMES (Nama Perangkat) from the Switch category and all
     * of its descendants, used as the switch dropdown choices. Network root
     * assets and sibling sub-categories are deliberately excluded. Empty until
     * switch assets are recorded.
     *
     * @return array<int, string>
     */
    private function switchOptions(): array
    {
        $categoryIds = $this->switchCategoryIds();

        if ($categoryIds === []) {
            return [];
        }

        return Asset::query()
            ->whereIn('category_id', $categoryIds)
            ->distinct()
            ->orderBy('name')
            ->pluck('name')
            ->filter(fn (string $name) => $name !== '' && $name !== '-')
            ->values()
            ->all();
    }

    /**
     * Find the Switch category id plus every descendant category id, shared
     * via the base Controller::categoryIdsByName() helper.
     *
     * @return list<int>
     */
    private function switchCategoryIds(): array
    {
        return $this->categoryIdsByName('Switch');
    }

    /**
     * Panel assets (Panel Power category + descendants) with their MCB
     * slots, powering the dependent Panel PDU / MCB Nomor dropdowns. Each
     * MCB carries taken_by (the customer id already using it, if any) so
     * the frontend can hide slots owned by other customers while keeping
     * the editing customer's own slot selectable.
     *
     * @return array<int, array<string, mixed>>
     */
    private function panelOptions(): array
    {
        $categoryIds = $this->categoryIdsByName('Panel Power');

        if ($categoryIds === []) {
            return [];
        }

        $takenByMcbId = [];

        // A plain loop: Collection::collapse() renumbers integer keys, so
        // flatMap() would destroy the mcb_id => customer_id mapping.
        Customer::query()
            ->whereNotNull('panel_mcb_id')
            ->orWhereNotNull('panel_mcb_id_2')
            ->get(['id', 'panel_mcb_id', 'panel_mcb_id_2'])
            ->each(function (Customer $customer) use (&$takenByMcbId) {
                if ($customer->panel_mcb_id !== null) {
                    $takenByMcbId[$customer->panel_mcb_id] = $customer->id;
                }

                if ($customer->panel_mcb_id_2 !== null) {
                    $takenByMcbId[$customer->panel_mcb_id_2] = $customer->id;
                }
            });

        return Asset::query()
            ->with('panelMcbs')
            ->whereIn('category_id', $categoryIds)
            ->orderBy('name')
            ->get(['id', 'name', 'floor', 'room'])
            ->map(fn (Asset $asset) => [
                'id' => $asset->id,
                'name' => $asset->name,
                'floor' => $asset->floor,
                'room' => $asset->room,
                'mcbs' => $asset->panelMcbs
                    ->sortBy('mcb_number', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->map(fn (PanelMcb $panelMcb) => [
                        'id' => $panelMcb->id,
                        'mcb_number' => $panelMcb->mcb_number,
                        'taken_by' => $takenByMcbId[$panelMcb->id] ?? null,
                    ])
                    ->all(),
            ])
            ->all();
    }

    /**
     * Build the customer query filtered by the request search parameters.
     */
    private function filteredQuery(Request $request): Builder
    {
        $search = $request->string('search')->toString();

        return Customer::query()
            ->with([
                'rack:id,rack_code,type,capacity,floor,room,pdu_outlet_1,pdu_outlet_2',
                'panelAsset:id,name',
                'panelMcb:id,asset_id,mcb_number',
                'panelAsset2:id,name',
                'panelMcb2:id,asset_id,mcb_number',
            ])
            ->when($search !== '', function (Builder $query) use ($search, $request) {
                $column = $this->resolveSearchColumn($request);

                if ($column === 'rack_code') {
                    $query->whereHas('rack', fn (Builder $rack) => $rack->where('rack_code', 'like', "%{$search}%"));

                    return;
                }

                if ($column === 'all') {
                    $query->where(function (Builder $query) use ($search) {
                        $query->where('customer_name', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%")
                            ->orWhere('location', 'like', "%{$search}%")
                            ->orWhere('service_type', 'like', "%{$search}%")
                            ->orWhere('order_number', 'like', "%{$search}%")
                            ->orWhere('sid_rack', 'like', "%{$search}%")
                            ->orWhere('ip_address', 'like', "%{$search}%")
                            ->orWhere('notes', 'like', "%{$search}%")
                            ->orWhereHas('rack', fn (Builder $rack) => $rack->where('rack_code', 'like', "%{$search}%"));
                    });

                    return;
                }

                $query->where(self::SEARCH_COLUMNS[$column], 'like', "%{$search}%");
            })
            ->orderBy('customer_name')
            ->orderBy('id');
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

        if ($column === 'rack_code') {
            return $column;
        }

        return isset(self::SEARCH_COLUMNS[$column]) ? $column : 'all';
    }
}
