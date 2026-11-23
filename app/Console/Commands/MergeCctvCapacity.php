<?php

namespace App\Console\Commands;

use App\Helpers\AssetSheetHelper;
use App\Models\Asset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeCctvCapacity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cctv:merge-capacity
                            {--dry-run : Show what would change without writing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge the HDD slot capacities of CCTV camera rows into their DVR/NVR device';

    /**
     * Legacy CCTV rows stored one HDD slot per channel, so the device only
     * kept the slot of the record it was created from. Gather every slot on
     * the device and clear the copies on the camera assets.
     */
    public function handle(): int
    {
        $cameraCategoryIds = AssetSheetHelper::cctvCameraCategoryIds();
        $deviceCategoryIds = array_values(array_diff(
            AssetSheetHelper::categoryIds('cctv'),
            $cameraCategoryIds,
        ));

        $devices = Asset::query()
            ->whereIn('category_id', $deviceCategoryIds)
            ->whereNull('parent_asset_id')
            ->with(['children' => fn ($query) => $query->whereIn('category_id', $cameraCategoryIds)])
            ->orderBy('id')
            ->get();

        $updated = 0;
        $cleared = 0;
        $dryRun = (bool) $this->option('dry-run');

        foreach ($devices as $device) {
            $slots = collect([$device->capacity])
                ->merge($device->children->pluck('capacity'));

            $merged = AssetSheetHelper::mergeCapacityValues($slots);
            $current = trim((string) $device->capacity);
            $dirtyCameras = $device->children
                ->filter(fn (Asset $camera): bool => trim((string) $camera->capacity) !== '');

            if ($merged === $current && $dirtyCameras->isEmpty()) {
                continue;
            }

            if ($dryRun) {
                $this->line(sprintf(
                    '%s: "%s" -> "%s" (%d camera row(s) would be cleared)',
                    $device->name,
                    $current,
                    $merged,
                    $dirtyCameras->count(),
                ));

                continue;
            }

            DB::transaction(function () use ($device, $merged, $dirtyCameras, &$updated, &$cleared): void {
                $device->update(['capacity' => $merged !== '' ? $merged : null]);
                $updated++;

                $dirtyCameras->each(function (Asset $camera) use (&$cleared): void {
                    $camera->update(['capacity' => null]);
                    $cleared++;
                });
            });
        }

        $this->info("Devices merged: {$updated}, camera capacity rows cleared: {$cleared}.");

        return self::SUCCESS;
    }
}
