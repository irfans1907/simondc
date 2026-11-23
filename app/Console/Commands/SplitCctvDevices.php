<?php

namespace App\Console\Commands;

use App\Actions\SyncAssetChecklistAction;
use App\Helpers\AssetSheetHelper;
use App\Models\Asset;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SplitCctvDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cctv:split-devices
                            {--dry-run : Show what would change without writing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Split legacy CCTV channel records into one device asset plus camera assets';

    /**
     * Rebuild legacy CCTV rows: each name/category group becomes one device
     * asset (DVR/NVR) and every record with camera data becomes a camera
     * asset in the Kamera category. Empty placeholder records are removed.
     */
    public function handle(): int
    {
        $cameraCategoryIds = AssetSheetHelper::cctvCameraCategoryIds();
        $cameraCategoryId = $cameraCategoryIds[0] ?? null;

        if ($cameraCategoryId === null) {
            $this->error('Kategori Kamera tidak ditemukan.');

            return self::FAILURE;
        }

        $deviceCategoryIds = array_values(array_diff(
            AssetSheetHelper::categoryIds('cctv'),
            $cameraCategoryIds,
        ));

        $groups = Asset::query()
            ->with('category')
            ->whereIn('category_id', $deviceCategoryIds)
            ->whereNull('parent_asset_id')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Asset $asset): string => mb_strtolower(trim($asset->name)).'|'.$asset->category_id);

        $devices = 0;
        $cameras = 0;
        $removed = 0;
        $dryRun = (bool) $this->option('dry-run');

        foreach ($groups as $members) {
            if (! $this->needsSplit($members)) {
                continue;
            }

            if ($dryRun) {
                $first = $members->first();
                $this->line("Would split {$first->name} ({$first->category?->name}) from {$members->count()} record(s).");

                continue;
            }

            DB::transaction(function () use ($members, $cameraCategoryId, &$devices, &$cameras, &$removed): void {
                $source = $members->first(fn (Asset $asset): bool => $asset->in_checklist
                    && $asset->checklist_category_id !== null) ?? $members->first();

                $device = Asset::create($this->deviceAttributes($source));
                $devices++;

                foreach ($members as $member) {
                    $details = is_array($member->details) ? $member->details : [];

                    if (! $this->hasCameraData($details)) {
                        $member->delete();
                        $removed++;

                        continue;
                    }

                    $member->update([
                        'name' => AssetSheetHelper::cctvCameraName($details, $device->name),
                        'category_id' => $cameraCategoryId,
                        'parent_asset_id' => $device->id,
                        'in_checklist' => false,
                        'in_maintenance' => false,
                        'checklist_category_id' => null,
                    ]);
                    $cameras++;
                }

                app(SyncAssetChecklistAction::class)->execute($device->fresh('category'));
            });
        }

        $this->info("Devices: {$devices}, cameras: {$cameras}, removed empty records: {$removed}.");

        return self::SUCCESS;
    }

    /**
     * A single record without camera data is already a device. Anything else
     * still carries legacy channel rows.
     *
     * @param  Collection<int, Asset>  $members
     */
    private function needsSplit(Collection $members): bool
    {
        if ($members->count() > 1) {
            return true;
        }

        $details = $members->first()?->details;

        return $this->hasCameraData(is_array($details) ? $details : []);
    }

    /**
     * Whether the record holds any per-camera value.
     *
     * @param  array<string, mixed>  $details
     */
    private function hasCameraData(array $details): bool
    {
        foreach ([
            'camera_no',
            'alias_kamera',
            'merek_kamera',
            'model_type',
            'sn',
            'analog_digital',
            'ip_kamera',
            'key_pass',
            'lantai_kamera',
        ] as $key) {
            if (trim((string) ($details[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Device fields copied from the record that owns the checklist settings.
     *
     * @return array<string, mixed>
     */
    private function deviceAttributes(Asset $source): array
    {
        return [
            'name' => $source->name,
            'category_id' => $source->category_id,
            'parent_asset_id' => null,
            'alias' => $source->alias,
            'ip_address' => $source->ip_address,
            'owner' => $source->owner,
            'brand' => $source->brand,
            'type' => $source->type,
            'model' => $source->model,
            'serial_number' => $source->serial_number,
            'capacity' => $source->capacity,
            'manufacture_year' => $source->manufacture_year,
            'operation_year' => $source->operation_year,
            'quantity' => $source->quantity,
            'floor' => $source->floor,
            'room' => $source->room,
            'notes' => $source->notes,
            'details' => [],
            'in_checklist' => $source->in_checklist,
            'in_maintenance' => $source->in_maintenance,
            'checklist_category_id' => $source->checklist_category_id,
        ];
    }
}
