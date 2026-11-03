<?php

namespace App\Http\Controllers;

use App\Events\MaintenanceChanged;
use App\Http\Requests\TroubleshootRequest;
use App\Models\Asset;
use App\Models\Maintenance;
use App\Models\Menu;
use App\Models\Troubleshoot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TroubleshootController extends Controller
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
        'result' => 'result',
        'ticket_number' => 'ticket_number',
    ];

    /**
     * Show the troubleshoot page (/assets/troubleshoot): the trouble
     * ticket log. Tickets are either created here manually or born from
     * a Bermasalah maintenance record (maintenance_id link).
     */
    public function index(Request $request): InertiaResponse
    {
        $canManage = $request->user()->hasPermissionTo('manage asset management');

        $childMenu = Menu::where('url', '/'.$request->path())->first();

        return Inertia::render('dynamic/assets/troubleshoot', [
            ...$this->childMenuProps($childMenu?->parent, $childMenu),
            'troubleshoots' => $this->filteredQuery($request)
                ->with(['asset:id,name', 'maintenance:id,maintenance_date,result'])
                ->paginate($request->integer('per_page', 10))
                ->withQueryString(),
            'assetOptions' => Asset::query()->orderBy('name')->get(['id', 'name']),
            'history' => $this->historyPayload($request),
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
     * Store a manually entered trouble ticket. A ticket born Solved must
     * already carry its work dates (validated); otherwise it starts
     * Unresolved with no close date.
     */
    public function store(TroubleshootRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Troubleshoot::create([
            'maintenance_id' => null,
            'asset_id' => $data['asset_id'],
            'troubleshoot_date' => $data['troubleshoot_date'],
            'started_date' => $data['started_date'] ?? null,
            'closed_date' => $data['result'] === 'Normal' ? ($data['closed_date'] ?? null) : null,
            'result' => $data['result'],
            'evidence_photo' => ($data['evidence_photo'] ?? null)?->store('troubleshoots', 'public'),
            'document' => ($data['document'] ?? null)?->store('troubleshoots', 'public'),
        ]);

        MaintenanceChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trouble ticket recorded.')]);

        return to_route('assets.troubleshoot');
    }

    /**
     * Update the given trouble ticket. The maintenance link is never
     * changed from this form. This is also the ONLY place a ticket gets
     * closed: picking Solved demands the work dates and flips the parent
     * maintenance record to result Normal + status Done (one-way sync,
     * Troubleshoot → Maintenance); switching back to Unresolved clears
     * the close date.
     */
    public function update(TroubleshootRequest $request, Troubleshoot $troubleshoot): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $troubleshoot) {
            $solved = $data['result'] === 'Normal';

            $troubleshoot->update([
                'asset_id' => $data['asset_id'],
                'troubleshoot_date' => $data['troubleshoot_date'],
                'started_date' => $data['started_date'] ?? null,
                'closed_date' => $solved ? ($data['closed_date'] ?? null) : null,
                'result' => $data['result'],
                'evidence_photo' => ($data['evidence_photo'] ?? null)?->store('troubleshoots', 'public')
                    ?? $troubleshoot->evidence_photo,
                'document' => ($data['document'] ?? null)?->store('troubleshoots', 'public')
                    ?? $troubleshoot->document,
            ]);

            if ($solved) {
                $troubleshoot->maintenance?->update([
                    'result' => 'Normal',
                    'status' => Maintenance::STATUS_DONE,
                ]);
            }
        });

        MaintenanceChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trouble ticket updated.')]);

        return to_route('assets.troubleshoot');
    }

    /**
     * Delete the given trouble ticket.
     */
    public function destroy(Troubleshoot $troubleshoot): RedirectResponse
    {
        Storage::disk('public')->delete(array_filter([
            $troubleshoot->evidence_photo,
            $troubleshoot->document,
        ]));

        $troubleshoot->delete();

        MaintenanceChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trouble ticket deleted.')]);

        return to_route('assets.troubleshoot');
    }

    /**
     * Export the filtered trouble tickets as a CSV file.
     */
    public function export(Request $request): StreamedResponse
    {
        $troubleshoots = $this->filteredQuery($request)
            ->with('asset:id,name')
            ->get();

        $headers = [
            'No', 'No Tiket', 'Nama Perangkat', 'Tanggal', 'Mulai', 'Selesai', 'Hasil', 'Foto Eviden', 'Dokumen',
        ];

        return Response::streamDownload(function () use ($troubleshoots, $headers) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, escape: '\\');

            $row = 0;

            foreach ($troubleshoots as $troubleshoot) {
                fputcsv($handle, [
                    ++$row,
                    $troubleshoot->ticket_number ?? '-',
                    $troubleshoot->asset?->name ?? '-',
                    $troubleshoot->troubleshoot_date,
                    $troubleshoot->started_date ?? '-',
                    $troubleshoot->closed_date ?? '-',
                    Troubleshoot::resultLabel($troubleshoot->result),
                    $troubleshoot->evidence_photo ?? '-',
                    $troubleshoot->document ?? '-',
                ], escape: '\\');
            }

            fclose($handle);
        }, 'troubleshoots-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Export one asset's full history (trouble tickets + maintenance
     * records) as a two-section CSV file.
     */
    public function exportHistory(Request $request): StreamedResponse
    {
        $history = $this->historyPayload($request);

        abort_if($history === null, 404);

        $filename = 'riwayat-gangguan-'.Str::slug($history['asset']['name']).'-'.now()->format('Y-m-d').'.csv';

        return Response::streamDownload(function () use ($history) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Riwayat Gangguan - '.$history['asset']['name']], escape: '\\');
            fputcsv($handle, ['No', 'No Tiket', 'Tanggal', 'Mulai', 'Selesai', 'Hasil', 'Asal', 'Foto Eviden', 'Dokumen'], escape: '\\');

            $row = 0;

            foreach ($history['tickets'] as $ticket) {
                fputcsv($handle, [
                    ++$row,
                    $ticket->ticket_number ?? '-',
                    $ticket->troubleshoot_date,
                    $ticket->started_date ?? '-',
                    $ticket->closed_date ?? '-',
                    Troubleshoot::resultLabel($ticket->result),
                    $ticket->maintenance
                        ? 'Maintenance '.$ticket->maintenance->maintenance_date
                        : 'Manual',
                    $ticket->evidence_photo ?? '-',
                    $ticket->document ?? '-',
                ], escape: '\\');
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Riwayat Maintenance'], escape: '\\');
            fputcsv($handle, ['No', 'Tanggal Mulai', 'Tanggal Beres', 'Status', 'Hasil'], escape: '\\');

            $row = 0;

            foreach ($history['maintenances'] as $maintenance) {
                fputcsv($handle, [
                    ++$row,
                    $maintenance->maintenance_date,
                    $maintenance->end_date ?? '-',
                    $maintenance->status,
                    $maintenance->result ?? '-',
                ], escape: '\\');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Build the per-asset history panel for ?history_asset=: every ticket
     * the asset ever had (with its origin) plus its maintenance records.
     *
     * @return array{
     *     asset: array<string, mixed>,
     *     tickets: Collection<int, Troubleshoot>,
     *     maintenances: Collection<int, Maintenance>
     * }|null
     */
    private function historyPayload(Request $request): ?array
    {
        $assetId = $request->integer('history_asset', 0);

        if ($assetId <= 0) {
            return null;
        }

        $asset = Asset::find($assetId);

        if ($asset === null) {
            return null;
        }

        return [
            'asset' => $asset->only('id', 'name'),
            'tickets' => Troubleshoot::query()
                ->where('asset_id', $asset->id)
                ->with('maintenance:id,maintenance_date')
                ->orderByDesc('troubleshoot_date')
                ->orderByDesc('id')
                ->get(),
            'maintenances' => Maintenance::query()
                ->where('asset_id', $asset->id)
                ->orderByDesc('maintenance_date')
                ->orderByDesc('id')
                ->get(['id', 'asset_id', 'maintenance_date', 'end_date', 'status', 'result']),
        ];
    }

    /**
     * Translate the Solved/Unresolved display labels used by the UI back
     * to the stored result strings, so typing either form matches rows.
     * Whole-word and case-insensitive — a plain str_ireplace would
     * corrupt "unresolved" because it contains the substring "solved".
     */
    private function resultSearchTerm(string $search): string
    {
        $replacements = [];

        foreach (Troubleshoot::RESULT_LABELS as $result => $label) {
            $replacements[strtolower($label)] = $result;
        }

        $pattern = '/\b(?:'.implode('|', array_map(
            fn (string $label) => preg_quote($label, '/'),
            array_keys($replacements),
        )).')\b/i';

        return preg_replace_callback(
            $pattern,
            fn (array $matches) => $replacements[strtolower($matches[0])],
            $search,
        ) ?? $search;
    }

    /**
     * Build the trouble ticket query filtered by the request search parameters.
     *
     * @return Builder<Troubleshoot>
     */
    private function filteredQuery(Request $request): Builder
    {
        $search = $request->string('search')->toString();

        return Troubleshoot::query()
            ->when($request->string('date')->toString(), function (Builder $query, string $date) {
                $query->whereDate('troubleshoot_date', $date);
            })
            ->when($search !== '', function (Builder $query) use ($search, $request) {
                $column = $this->resolveSearchColumn($request);
                $resultTerm = $this->resultSearchTerm($search);

                if ($column === 'asset_name') {
                    $query->whereHas('asset', fn (Builder $asset) => $asset->where('name', 'like', "%{$search}%"));

                    return;
                }

                if ($column === 'all') {
                    $query->where(function (Builder $query) use ($search, $resultTerm) {
                        $query->where('troubleshoot_date', 'like', "%{$search}%")
                            ->orWhere('result', 'like', "%{$resultTerm}%")
                            ->orWhere('ticket_number', 'like', "%{$search}%")
                            ->orWhereHas('asset', fn (Builder $asset) => $asset->where('name', 'like', "%{$search}%"));
                    });

                    return;
                }

                if ($column === 'result') {
                    $query->where('result', 'like', "%{$resultTerm}%");

                    return;
                }

                $query->where(self::SEARCH_COLUMNS[$column], 'like', "%{$search}%");
            })
            ->orderByDesc('troubleshoot_date')
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
