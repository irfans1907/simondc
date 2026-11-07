<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\PowerLoadReading;
use App\Models\PowerLoadRow;
use App\Models\PowerPanel;
use App\Models\PowerPanelReading;
use App\Models\PowerPdu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MonthlyCheckController extends Controller
{
    /**
     * Show the monthly power-consumption / PUE report.
     *
     * Tabs: lantai 2, lantai 4, and PUE & beban DC.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $canManage = $user->hasPermissionTo('manage checklist');

        $tab = $request->string('tab')->toString();
        if (! in_array($tab, ['lantai2', 'lantai4', 'pue'], true)) {
            $tab = 'lantai2';
        }

        $year = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);
        $period = sprintf('%04d-%02d', $year, $month);

        $pdus = PowerPdu::query()
            ->where('is_active', true)
            ->orderBy('floor')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->with(['rows' => fn ($query) => $query->orderBy('sort_order')])
            ->get();

        $pdusByFloor = [
            2 => $pdus->where('floor', 2)->values(),
            4 => $pdus->where('floor', 4)->values(),
        ];

        // Load readings per PDU row for the selected year.
        $readings = PowerLoadReading::query()
            ->where('period', 'like', $year.'-%')
            ->get()
            ->keyBy('power_load_row_id');

        $rowsByPdu = [];
        foreach ($pdus as $pdu) {
            $rows = [];
            foreach ($pdu->rows as $row) {
                $reading = $readings->get($row->id);
                $rows[] = [
                    'id' => $row->id,
                    'customer' => $row->customer,
                    'rack' => $row->rack,
                    'rack_type' => $row->rack_type,
                    'mcb' => $row->mcb,
                    'capacity_amp' => $row->capacity_amp,
                    'phase' => $row->phase,
                    'service' => $row->service,
                    'sort_order' => $row->sort_order,
                    'ampere' => $reading?->ampere,
                    'readings_by_month' => $this->readingsByMonth($row->id, $year),
                ];
            }
            $rowsByPdu[$pdu->id] = $rows;
        }

        $months = $this->monthRange($year);

        $panels = [];
        $panelReadings = [];
        if ($tab === 'pue') {
            $panels = PowerPanel::query()
                ->where('is_active', true)
                ->orderByRaw("FIELD(panel_group, 'MDP','SDP','IO','PAC','UPS','PDU','UTILITY')")
                ->orderBy('sort_order')
                ->get();

            $panelReadings = PowerPanelReading::query()
                ->where('period', $period)
                ->get()
                ->keyBy('power_panel_id');
        }

        $childMenu = Menu::where('url', '/checklist/monthlycheck')->first();

        return Inertia::render('dynamic/checklist/monthlycheck', [
            ...$this->childMenuProps($childMenu?->parent, $childMenu),
            'tab' => $tab,
            'year' => $year,
            'month' => $month,
            'period' => $period,
            'months' => $months,
            'pdusByFloor' => $pdusByFloor,
            'rowsByPdu' => $rowsByPdu,
            'panels' => $panels,
            'panelReadings' => $panelReadings,
            'canManage' => $canManage,
        ]);
    }

    /**
     * Store monthly ampere readings for one PDU (all its rows).
     */
    public function storeLoad(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pdu_id' => ['required', 'exists:power_pdus,id'],
            'readings' => ['required', 'array'],
            'readings.*.row_id' => [
                'required',
                'integer',
                'exists:power_load_rows,id',
            ],
            'readings.*.period' => [
                'required',
                'string',
                'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
            ],
            'readings.*.ampere' => ['nullable', 'numeric', 'between:0,9999'],
        ]);

        $pduId = $data['pdu_id'];
        $userId = $request->user()->id;

        $rowIds = PowerLoadRow::query()
            ->where('power_pdu_id', $pduId)
            ->pluck('id')
            ->toArray();

        foreach ($data['readings'] as $reading) {
            if (! in_array($reading['row_id'], $rowIds, true)) {
                continue;
            }

            $ampere = $reading['ampere'] ?? null;

            if ($ampere === null) {
                PowerLoadReading::query()
                    ->where('power_load_row_id', $reading['row_id'])
                    ->where('period', $reading['period'])
                    ->delete();

                continue;
            }

            PowerLoadReading::updateOrCreate(
                [
                    'power_load_row_id' => $reading['row_id'],
                    'period' => $reading['period'],
                ],
                ['ampere' => $ampere, 'user_id' => $userId],
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Beban daya tersimpan.')]);

        return back();
    }

    /**
     * Store monthly R/S/T readings for one panel.
     */
    public function storePanel(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'panel_id' => ['required', 'exists:power_panels,id'],
            'period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'amp_r' => ['nullable', 'numeric', 'between:0,9999'],
            'amp_s' => ['nullable', 'numeric', 'between:0,9999'],
            'amp_t' => ['nullable', 'numeric', 'between:0,9999'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $period = $data['period'];
        $year = (int) substr($period, 0, 4);
        $month = (int) substr($period, 5, 2);
        $userId = $request->user()->id;

        $ampR = $data['amp_r'] ?? null;
        $ampS = $data['amp_s'] ?? null;
        $ampT = $data['amp_t'] ?? null;
        $note = trim((string) ($data['note'] ?? ''));

        if ($ampR === null && $ampS === null && $ampT === null && $note === '') {
            PowerPanelReading::query()
                ->where('power_panel_id', $data['panel_id'])
                ->where('period', $period)
                ->delete();
        } else {
            PowerPanelReading::updateOrCreate(
                ['power_panel_id' => $data['panel_id'], 'period' => $period],
                [
                    'amp_r' => $ampR,
                    'amp_s' => $ampS,
                    'amp_t' => $ampT,
                    'note' => $note !== '' ? $note : null,
                    'user_id' => $userId,
                ],
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pembacaan panel tersimpan.')]);

        return redirect()->route('checklist.monthlycheck', [
            'query' => ['tab' => 'pue', 'year' => $year, 'month' => $month],
        ]);
    }

    /**
     * Export the selected tab as CSV following the workbook column layout.
     */
    public function export(Request $request)
    {
        $tab = $request->string('tab')->toString();
        $year = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);
        $period = sprintf('%04d-%02d', $year, $month);

        $headers = [];
        $rows = [];
        $fileName = 'power-consumption';

        if ($tab === 'lantai2' || $tab === 'lantai4') {
            $floor = $tab === 'lantai2' ? 2 : 4;
            $pdus = PowerPdu::query()
                ->where('floor', $floor)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('code')
                ->with(['rows' => fn ($query) => $query->orderBy('sort_order')])
                ->get();

            $monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            $headers = [
                'No', 'Customer', 'Rack', 'Type Rack', 'PDU', 'MCB', 'Amp', 'Phase', 'Layanan',
                ...$monthLabels,
            ];

            $row = 0;
            foreach ($pdus as $pdu) {
                foreach ($pdu->rows as $r) {
                    $readings = PowerLoadReading::query()
                        ->where('power_load_row_id', $r->id)
                        ->orderBy('period')
                        ->get()
                        ->keyBy('period');

                    $line = [
                        ++$row,
                        $r->customer ?? '-',
                        $r->rack ?? '-',
                        $r->rack_type ?? '-',
                        $pdu->code,
                        $r->mcb ?? '-',
                        $r->capacity_amp ?? '-',
                        $r->phase ?? '-',
                        $r->service ?? '-',
                    ];

                    foreach ($monthLabels as $label) {
                        $periodKey = sprintf('%04d-%02d', $year, array_search($label, $monthLabels, true) + 1);
                        $reading = $readings->get($periodKey);
                        $line[] = $reading?->ampere ?? '';
                    }

                    $rows[] = $line;
                }
            }

            $fileName = 'power-consumption-lantai-'.$floor;
        } elseif ($tab === 'pue') {
            $panels = PowerPanel::query()
                ->where('is_active', true)
                ->orderByRaw("FIELD(panel_group, 'MDP','SDP','IO','PAC','UPS','PDU','UTILITY')")
                ->orderBy('sort_order')
                ->get();

            $readings = PowerPanelReading::query()
                ->where('period', $period)
                ->get()
                ->keyBy('power_panel_id');

            $headers = [
                'No', 'Group', 'Nama Perangkat', 'Kapasitas', 'Satuan', 'R', 'S', 'T',
                'kW', 'kVA', 'Kapasitas 80%', 'Idle', 'Used %', 'Keterangan',
            ];

            $row = 0;
            foreach ($panels as $panel) {
                $reading = $readings->get($panel->id);
                $r = $reading?->amp_r ?? null;
                $s = $reading?->amp_s ?? null;
                $t = $reading?->amp_t ?? null;

                $kw = null;
                $kva = null;
                $capacity80 = null;
                $idle = null;
                $used = null;

                if ($r !== null || $s !== null || $t !== null) {
                    $kw = (($r ?? 0) + ($s ?? 0) + ($t ?? 0)) * 220 / 1000 * 0.8;
                    $kva = $kw / 0.8;
                }

                if ($panel->capacity !== null) {
                    $capacity80 = $panel->capacity * 0.8;
                    $maxAmp = $r !== null ? max($r ?? 0, $s ?? 0, $t ?? 0) : 0;
                    if ($panel->unit === 'KVA') {
                        $idle = $capacity80 - ($kva ?? 0);
                    } else {
                        $idle = $capacity80 - $maxAmp;
                    }
                    $used = $capacity80 > 0 ? (1 - $idle / $capacity80) * 100 : 0;
                }

                $rows[] = [
                    ++$row,
                    $panel->panel_group,
                    $panel->name,
                    $panel->capacity ?? '-',
                    $panel->unit ?? '-',
                    $r ?? '',
                    $s ?? '',
                    $t ?? '',
                    $kw !== null ? number_format($kw, 2, '.', '') : '',
                    $kva !== null ? number_format($kva, 2, '.', '') : '',
                    $capacity80 !== null ? number_format($capacity80, 2, '.', '') : '',
                    $idle !== null ? number_format($idle, 2, '.', '') : '',
                    $used !== null ? number_format($used, 2, '.', '') : '',
                    $panel->note ?? '',
                ];
            }

            $fileName = 'power-consumption-pue-'.$period;
        }

        return Response::streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, escape: '\\');

            foreach ($rows as $line) {
                fputcsv($handle, $line, escape: '\\');
            }

            fclose($handle);
        }, $fileName.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Monthly ampere readings for one row across a year.
     *
     * @return array<int, float|null>
     */
    private function readingsByMonth(int $rowId, int $year): array
    {
        $readings = PowerLoadReading::query()
            ->where('power_load_row_id', $rowId)
            ->where('period', 'like', $year.'-%')
            ->get()
            ->keyBy('period');

        $months = [];

        for ($m = 1; $m <= 12; $m++) {
            $period = sprintf('%04d-%02d', $year, $m);
            $months[$m] = $readings->get($period)?->ampere;
        }

        return $months;
    }

    /**
     * Labels for the 12 months of a year.
     *
     * @return list<string>
     */
    private function monthRange(int $year): array
    {
        $labels = [];

        for ($m = 1; $m <= 12; $m++) {
            $labels[] = sprintf('%04d-%02d', $year, $m);
        }

        return $labels;
    }
}
