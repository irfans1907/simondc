<?php

namespace App\Http\Controllers;

use App\Events\MaintenanceChanged;
use App\Http\Requests\MaintenanceRequest;
use App\Models\Asset;
use App\Models\Maintenance;
use App\Models\Menu;
use App\Models\Troubleshoot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaintenanceController extends Controller
{
    /**
     * Whitelisted text-search columns: request value => database column.
     * "asset_name" lives on the related asset, so filteredQuery() resolves
     * it through whereHas(); dates are handled by the dedicated "date"
     * filter (whereDate), not by a text column.
     *
     * @var array<string, string>
     */
    private const SEARCH_COLUMNS = [
        'status' => 'status',
        'result' => 'result',
    ];

    /**
     * Show the maintenance page (/assets/maintenance): the log of asset
     * maintenance records. A Bermasalah result also carries a trouble
     * ticket stored as a Troubleshoot record, created in the same step.
     */
    public function index(Request $request): InertiaResponse
    {
        $this->advanceDueStatuses();

        $canManage = $request->user()->hasPermissionTo('manage asset management');

        $childMenu = Menu::where('url', '/'.$request->path())->first();

        return Inertia::render('dynamic/assets/maintenance', [
            ...$this->childMenuProps($childMenu?->parent, $childMenu),
            'maintenances' => $this->filteredQuery($request)
                ->with(['asset:id,name', 'troubleshoot:id,maintenance_id,result'])
                ->paginate($request->integer('per_page', 10))
                ->withQueryString(),
            'assetOptions' => Asset::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'search_column' => $this->resolveSearchColumn($request),
                'date' => $request->string('date')->toString(),
                'per_page' => $request->integer('per_page', 10),
            ],
            'canManage' => $canManage,
        ]);
    }

    /**
     * Store a new maintenance record. The status follows the result: a
     * Normal outcome lands Done, a Bermasalah one stays On Progress and
     * opens a trouble ticket on the Troubleshoot page automatically
     * (asset and work dates inherited).
     */
    public function store(MaintenanceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $result = $data['result'] ?? null;

            $maintenance = Maintenance::create([
                'asset_id' => $data['asset_id'],
                'maintenance_date' => $data['maintenance_date'],
                'end_date' => $data['end_date'] ?? null,
                'status' => $this->statusForResult($result, $data['status']),
                'result' => $result,
                'evidence_photo' => ($data['evidence_photo'] ?? null)?->store('maintenances', 'public'),
                'document' => ($data['document'] ?? null)?->store('maintenances', 'public'),
            ]);

            if ($result === 'Bermasalah') {
                $this->createTroubleTicket($maintenance);
            }
        });

        MaintenanceChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Maintenance recorded.')]);

        return to_route('assets.maintenance');
    }

    /**
     * Update the given maintenance record. The status follows the result
     * (Normal → Done, Bermasalah → On Progress + a ticket). A ticket is
     * only ever opened or re-opened from here — CLOSING one belongs to
     * the Troubleshoot page (which flips this record back to Normal);
     * while its ticket is unresolved the request cannot change the
     * result at all (guarded in MaintenanceRequest::after()).
     */
    public function update(MaintenanceRequest $request, Maintenance $maintenance): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $maintenance) {
            $result = $data['result'] ?? null;
            $troubleshoot = $maintenance->troubleshoot;
            $ticketUnresolved = $troubleshoot !== null && $troubleshoot->result === 'Masih Bermasalah';

            $maintenance->update([
                'asset_id' => $data['asset_id'],
                'maintenance_date' => $data['maintenance_date'],
                'end_date' => $data['end_date'] ?? null,
                'status' => $this->statusForResult($result, $data['status']),
                'result' => $result,
                'evidence_photo' => ($data['evidence_photo'] ?? null)?->store('maintenances', 'public')
                    ?? $maintenance->evidence_photo,
                'document' => ($data['document'] ?? null)?->store('maintenances', 'public')
                    ?? $maintenance->document,
            ]);

            if ($ticketUnresolved) {
                return;
            }

            if ($result === 'Bermasalah') {
                if ($troubleshoot === null) {
                    $this->createTroubleTicket($maintenance);
                } else {
                    $troubleshoot->update(['result' => 'Masih Bermasalah', 'closed_date' => null]);
                }
            }
        });

        MaintenanceChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Maintenance updated.')]);

        return to_route('assets.maintenance');
    }

    /**
     * Delete the given maintenance record. Its trouble ticket survives
     * with maintenance_id set to NULL (the ticket owns asset + date).
     */
    public function destroy(Maintenance $maintenance): RedirectResponse
    {
        Storage::disk('public')->delete(array_filter([
            $maintenance->evidence_photo,
            $maintenance->document,
        ]));

        $maintenance->delete();

        MaintenanceChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Maintenance deleted.')]);

        return to_route('assets.maintenance');
    }

    /**
     * Export the filtered maintenance records as a CSV file.
     */
    public function export(Request $request): StreamedResponse
    {
        $maintenances = $this->filteredQuery($request)
            ->with(['asset:id,name', 'troubleshoot:id,maintenance_id,result'])
            ->get();

        $headers = [
            'No', 'Nama Perangkat', 'Tanggal Mulai', 'Tanggal Beres', 'Status', 'Hasil', 'Foto Eviden', 'Dokumen', 'Tiket Gangguan',
        ];

        return Response::streamDownload(function () use ($maintenances, $headers) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, escape: '\\');

            $row = 0;

            foreach ($maintenances as $maintenance) {
                fputcsv($handle, [
                    ++$row,
                    $maintenance->asset?->name ?? '-',
                    $maintenance->maintenance_date,
                    $maintenance->end_date ?? '-',
                    $maintenance->status,
                    $maintenance->result ?? '-',
                    $maintenance->evidence_photo ?? '-',
                    $maintenance->document ?? '-',
                    Troubleshoot::resultLabel($maintenance->troubleshoot?->result) ?? '-',
                ], escape: '\\');
            }

            fclose($handle);
        }, 'maintenances-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Move Scheduled maintenances whose date has arrived to On Progress.
     * This on-read copy keeps every environment accurate without a cron
     * scheduler; maintenance:advance-status runs the same query hourly.
     */
    private function advanceDueStatuses(): void
    {
        Maintenance::query()
            ->where('status', Maintenance::STATUS_SCHEDULED)
            ->whereDate('maintenance_date', '<=', today())
            ->update(['status' => Maintenance::STATUS_IN_PROGRESS]);
    }

    /**
     * The status always follows the result: a Normal outcome finishes the
     * record (Done), a Bermasalah one keeps it On Progress until its
     * ticket is closed; a null result keeps the requested stage (Done is
     * rejected by MaintenanceRequest when no outcome is recorded).
     */
    private function statusForResult(?string $result, string $requested): string
    {
        return match ($result) {
            'Normal' => Maintenance::STATUS_DONE,
            'Bermasalah' => Maintenance::STATUS_IN_PROGRESS,
            default => $requested === Maintenance::STATUS_DONE
                ? Maintenance::STATUS_IN_PROGRESS
                : $requested,
        };
    }

    /**
     * Open the trouble ticket of a Bermasalah maintenance. Asset and the
     * work dates are inherited (the gangguan date is the date the work
     * finished, falling back to the start date); evidence is filled in
     * later on the Troubleshoot page, so the ticket starts as Masih
     * Bermasalah.
     */
    private function createTroubleTicket(Maintenance $maintenance): void
    {
        Troubleshoot::create([
            'maintenance_id' => $maintenance->id,
            'asset_id' => $maintenance->asset_id,
            'troubleshoot_date' => $maintenance->end_date ?? $maintenance->maintenance_date,
            'result' => 'Masih Bermasalah',
        ]);
    }

    /**
     * Build the maintenance query filtered by the request search parameters.
     *
     * @return Builder<Maintenance>
     */
    private function filteredQuery(Request $request): Builder
    {
        $search = $request->string('search')->toString();

        return Maintenance::query()
            ->when($request->string('date')->toString(), function (Builder $query, string $date) {
                $query->whereDate('maintenance_date', $date);
            })
            ->when($search !== '', function (Builder $query) use ($search, $request) {
                $column = $this->resolveSearchColumn($request);

                if ($column === 'asset_name') {
                    $query->whereHas('asset', fn (Builder $asset) => $asset->where('name', 'like', "%{$search}%"));

                    return;
                }

                if ($column === 'all') {
                    $query->where(function (Builder $query) use ($search) {
                        $query->where('maintenance_date', 'like', "%{$search}%")
                            ->orWhere('status', 'like', "%{$search}%")
                            ->orWhere('result', 'like', "%{$search}%")
                            ->orWhereHas('asset', fn (Builder $asset) => $asset->where('name', 'like', "%{$search}%"));
                    });

                    return;
                }

                $query->where(self::SEARCH_COLUMNS[$column], 'like', "%{$search}%");
            })
            ->orderByDesc('maintenance_date')
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

        if ($column === 'asset_name') {
            return 'asset_name';
        }

        return isset(self::SEARCH_COLUMNS[$column]) ? $column : 'all';
    }
}
