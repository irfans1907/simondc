<?php

namespace App\Http\Controllers;

use App\Events\CustomersChanged;
use App\Http\Requests\VlanTagRequestRequest;
use App\Models\Asset;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\VlanTagRequest;
use App\Models\VlanTagRequestSwitch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VlanTagRequestController extends Controller
{
    /**
     * Whitelisted text-search columns: request value => database column.
     * "customer_name" lives on the related customer, so filteredQuery()
     * resolves it through whereHas().
     *
     * @var array<string, string>
     */
    private const SEARCH_COLUMNS = [
        'order_number' => 'order_number',
        'sid' => 'sid',
        'vlan' => 'vlan',
        'mode' => 'mode',
        'service_name' => 'service_name',
    ];

    /**
     * Show the VLAN tag request page (/customer/vlanreq): the list of
     * customer tag requests together with their switch/port rows.
     */
    public function index(Request $request): InertiaResponse
    {
        $canManage = $request->user()->hasPermissionTo('manage customer list');

        $childMenu = Menu::where('url', '/'.$request->path())->first();

        return Inertia::render('dynamic/customer/vlanreq', [
            ...$this->childMenuProps($childMenu?->parent, $childMenu),
            'vlanTagRequests' => $this->filteredQuery($request)
                ->with(['customer:id,customer_name', 'switches.asset:id,name'])
                ->paginate($request->integer('per_page', 10))
                ->withQueryString(),
            'customers' => Customer::query()
                ->orderBy('customer_name')
                ->get(['id', 'customer_name', 'sid_rack']),
            'networkAssets' => Asset::query()
                ->whereIn('category_id', $this->categoryIdsByName('Network'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'search_column' => $this->resolveSearchColumn($request),
                'per_page' => $request->integer('per_page', 10),
            ],
            'canManage' => $canManage,
        ]);
    }

    /**
     * Store a new VLAN tag request together with its switch rows. The
     * form preselects Network switches only; LAG requests may add rows.
     */
    public function store(VlanTagRequestRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $vlanRequest = VlanTagRequest::create([
                'customer_id' => $data['customer_id'],
                'order_number' => $data['order_number'],
                'sid' => $data['sid'],
                'service_name' => $data['service_name'],
                'vlan' => $data['vlan'],
                'mode' => $data['mode'],
            ]);

            $vlanRequest->switches()->createMany($this->switchRows($data['switches']));
        });

        CustomersChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('VLAN tag request created.')]);

        return to_route('customer.vlanreq');
    }

    /**
     * Update the given VLAN tag request. Its switch rows are rebuilt
     * wholesale — they are plain value rows nothing else references.
     */
    public function update(VlanTagRequestRequest $request, VlanTagRequest $vlanTagRequest): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $vlanTagRequest) {
            $vlanTagRequest->update([
                'customer_id' => $data['customer_id'],
                'order_number' => $data['order_number'],
                'sid' => $data['sid'],
                'service_name' => $data['service_name'],
                'vlan' => $data['vlan'],
                'mode' => $data['mode'],
            ]);

            $newRows = $this->switchRows($data['switches']);
            $existing = $vlanTagRequest->switches()->orderBy('id')->get();

            foreach ($newRows as $index => $row) {
                $existingSwitch = $existing[$index] ?? null;

                if ($existingSwitch) {
                    $existingSwitch->update($row);
                } else {
                    $vlanTagRequest->switches()->create($row);
                }
            }

            // Hapus switch yang ada di luar jumlah baris baru (baris yang dikosongkan).
            foreach ($existing->slice(count($newRows)) as $leftover) {
                $leftover->delete();
            }
        });

        CustomersChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('VLAN tag request updated.')]);

        return to_route('customer.vlanreq');
    }

    /**
     * Delete the given VLAN tag request (its switch rows cascade).
     */
    public function destroy(VlanTagRequest $vlanTagRequest): RedirectResponse
    {
        $vlanTagRequest->delete();

        CustomersChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('VLAN tag request deleted.')]);

        return to_route('customer.vlanreq');
    }

    /**
     * Export the filtered VLAN tag requests as a CSV file. One row per
     * request; multiple switches/ports are joined with "; ".
     */
    public function export(Request $request): StreamedResponse
    {
        $vlanRequests = $this->filteredQuery($request)
            ->with(['customer:id,customer_name', 'switches.asset:id,name'])
            ->get();

        $headers = [
            'No', 'Nomor Order', 'SID', 'Nama Pelanggan', 'Nama Layanan', 'VLAN', 'Mode', 'Switch', 'Port Number',
        ];

        return Response::streamDownload(function () use ($vlanRequests, $headers) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, escape: '\\');

            $row = 0;

            foreach ($vlanRequests as $vlanRequest) {
                fputcsv($handle, [
                    ++$row,
                    $vlanRequest->order_number,
                    $vlanRequest->sid,
                    $vlanRequest->customer?->customer_name ?? '-',
                    $vlanRequest->service_name,
                    $vlanRequest->vlan,
                    $vlanRequest->mode,
                    $vlanRequest->switches->map(fn (VlanTagRequestSwitch $switch) => $switch->asset?->name ?? '-')->implode('; '),
                    $vlanRequest->switches->map(fn (VlanTagRequestSwitch $switch) => $switch->port_number)->implode('; '),
                ], escape: '\\');
            }

            fclose($handle);
        }, 'vlan-tag-requests-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Map validated switch rows to their child-row attributes.
     *
     * @param  array<int, array<string, mixed>>  $switches
     * @return array<int, array<string, mixed>>
     */
    private function switchRows(array $switches): array
    {
        return collect($switches)
            ->map(fn (array $switch) => [
                'asset_id' => $switch['asset_id'],
                'port_number' => $switch['port_number'],
            ])
            ->all();
    }

    /**
     * Build the request query filtered by the search parameters.
     *
     * @return Builder<VlanTagRequest>
     */
    private function filteredQuery(Request $request): Builder
    {
        $search = $request->string('search')->toString();

        return VlanTagRequest::query()
            ->when($search !== '', function (Builder $query) use ($search, $request) {
                $column = $this->resolveSearchColumn($request);

                if ($column === 'customer_name') {
                    $query->whereHas('customer', fn (Builder $customer) => $customer->where('customer_name', 'like', "%{$search}%"));

                    return;
                }

                if ($column === 'all') {
                    $query->where(function (Builder $query) use ($search) {
                        $query->where('order_number', 'like', "%{$search}%")
                            ->orWhere('sid', 'like', "%{$search}%")
                            ->orWhere('vlan', 'like', "%{$search}%")
                            ->orWhere('mode', 'like', "%{$search}%")
                            ->orWhere('service_name', 'like', "%{$search}%")
                            ->orWhereHas('customer', fn (Builder $customer) => $customer->where('customer_name', 'like', "%{$search}%"));
                    });

                    return;
                }

                $query->where(self::SEARCH_COLUMNS[$column], 'like', "%{$search}%");
            })
            ->orderByDesc('id');
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

        if ($column === 'customer_name') {
            return 'customer_name';
        }

        return isset(self::SEARCH_COLUMNS[$column]) ? $column : 'all';
    }
}
