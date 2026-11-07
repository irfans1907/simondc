<?php

namespace Database\Seeders;

use App\Models\PowerLoadRow;
use App\Models\PowerPanel;
use App\Models\PowerPdu;
use Illuminate\Database\Seeder;

/**
 * Seeds the monthly power consumption master data extracted from the
 * Lantai 2 / Lantai 4 workbooks and the DC Lembong PUE workbook.
 *
 * Monthly readings are intentionally left empty so the module starts
 * without data.
 */
class PowerConsumptionSeeder extends Seeder
{
    /**
     * Run the seeder.
     */
    public function run(): void
    {
        $data = require database_path('seeders/data/power_consumption.php');

        foreach ($data['pdus'] as $pduData) {
            $pdu = PowerPdu::updateOrCreate(
                ['floor' => $pduData['floor'], 'code' => $pduData['code']],
                [
                    'name' => $pduData['name'],
                    'sort_order' => $pduData['sort_order'],
                    'is_active' => true,
                ],
            );

            $keptRowIds = [];
            $sortOrder = 0;

            foreach ($pduData['rows'] as $rowData) {
                $row = PowerLoadRow::updateOrCreate(
                    [
                        'power_pdu_id' => $pdu->id,
                        'rack' => $rowData['rack'],
                        'mcb' => $rowData['mcb'],
                    ],
                    [
                        'customer' => $rowData['customer'],
                        'rack_type' => $rowData['rack_type'],
                        'capacity_amp' => $rowData['capacity_amp'],
                        'phase' => $rowData['phase'],
                        'service' => $rowData['service'],
                        'sort_order' => $sortOrder++,
                    ],
                );

                $keptRowIds[] = $row->id;
            }

            $pdu->rows()
                ->whereNotIn('id', $keptRowIds)
                ->delete();
        }

        $keptPanelIds = [];
        $sortOrder = 0;

        foreach ($data['panels'] as $panelData) {
            $panel = PowerPanel::updateOrCreate(
                ['panel_group' => $panelData['panel_group'], 'name' => $panelData['name']],
                [
                    'capacity' => $panelData['capacity'],
                    'unit' => $panelData['unit'],
                    'note' => $panelData['note'],
                    'sort_order' => $panelData['sort_order'],
                    'is_active' => true,
                ],
            );

            $keptPanelIds[] = $panel->id;
        }

        PowerPanel::whereNotIn('id', $keptPanelIds)->delete();
    }
}
