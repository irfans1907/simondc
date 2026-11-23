<?php

namespace App\Console\Commands;

use App\Actions\SyncAssetChecklistAction;
use App\Helpers\AssetSheetHelper;
use App\Models\Asset;
use App\Models\Customer;
use App\Models\Maintenance;
use App\Models\Troubleshoot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeCctvDuplicates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cctv:merge-duplicate-devices
                            {--dry-run : Show what would change without writing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge CCTV device records that share the same identity into one device';

    /**
     * Devices created by the legacy create form could duplicate one physical
     * DVR: same name, category, brand, IP, serial number, and model. Keep one
     * device, merge its HDD capacities, move cameras and references, then
     * re-sync the checklist.
     */
    public function handle(): int
    {
        $cameraCategoryIds = AssetSheetHelper::cctvCameraCategoryIds();
        $deviceCategoryIds = array_values(array_diff(
            AssetSheetHelper::categoryIds('cctv'),
            $cameraCategoryIds,
        ));

        $groups = Asset::query()
            ->whereIn('category_id', $deviceCategoryIds)
            ->whereNull('parent_asset_id')
            ->with('category')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Asset $asset): string => AssetSheetHelper::cctvDeviceIdentityKey($asset));

        $merged = 0;
        $removed = 0;
        $dryRun = (bool) $this->option('dry-run');

        foreach ($groups as $group) {
            if ($group->count() < 2) {
                continue;
            }

            // Prefer the device that already owns the checklist settings.
            $survivor = $group->first(fn (Asset $asset): bool => $asset->in_checklist
                && $asset->checklist_category_id !== null) ?? $group->first();
            $duplicates = $group
                ->reject(fn (Asset $asset): bool => $asset->id === $survivor->id)
                ->values();
            $duplicateIds = $duplicates->pluck('id')->all();
            $capacity = AssetSheetHelper::mergeCapacityValues($group->pluck('capacity'));

            if ($dryRun) {
                $this->line(sprintf(
                    'Merge device #%s into #%d %s (capacity: "%s")',
                    implode(', #', $duplicateIds),
                    $survivor->id,
                    $survivor->name,
                    $capacity,
                ));

                continue;
            }

            DB::transaction(function () use ($survivor, $duplicates, $duplicateIds, $capacity, &$merged, &$removed): void {
                Asset::query()
                    ->whereIn('parent_asset_id', $duplicateIds)
                    ->update(['parent_asset_id' => $survivor->id]);

                Maintenance::query()->whereIn('asset_id', $duplicateIds)->update(['asset_id' => $survivor->id]);
                Troubleshoot::query()->whereIn('asset_id', $duplicateIds)->update(['asset_id' => $survivor->id]);
                Customer::query()->whereIn('panel_asset_id', $duplicateIds)->update(['panel_asset_id' => $survivor->id]);
                Customer::query()->whereIn('panel_asset_id_2', $duplicateIds)->update(['panel_asset_id_2' => $survivor->id]);

                $survivor->update(['capacity' => $capacity !== '' ? $capacity : null]);

                foreach ($duplicates as $duplicate) {
                    $duplicate->delete();
                    $removed++;
                }

                app(SyncAssetChecklistAction::class)->execute($survivor->fresh('category'));
                $merged++;
            });
        }

        $this->info("Duplicate groups merged: {$merged}, device rows removed: {$removed}.");

        return self::SUCCESS;
    }
}
