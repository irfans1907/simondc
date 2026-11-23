<?php

namespace App\Console\Commands;

use App\Actions\SyncAssetChecklistAction;
use App\Models\Asset;
use App\Models\ChecklistCategory;
use App\Models\ChecklistItem;
use Illuminate\Console\Command;

class SyncAssetChecklists extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checklist:sync-assets
                            {--deactivate-legacy : Deactivate the config-seeded infrastructure items after syncing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate checklist items from the asset templates';

    /**
     * Sync every asset, then optionally retire the legacy infrastructure
     * items seeded from config/checklist.php.
     */
    public function handle(SyncAssetChecklistAction $sync): int
    {
        $synced = 0;

        Asset::query()
            ->with('category.parent')
            ->chunkById(100, function ($assets) use ($sync, &$synced): void {
                foreach ($assets as $asset) {
                    $sync->execute($asset);
                    $synced++;
                }
            });

        $this->info("Synced {$synced} asset(s).");

        if ($this->option('deactivate-legacy')) {
            // Category-scoped checklists (FAP panels) keep their config
            // items active; everything else falls back to asset items.
            $scopedCategoryIds = ChecklistCategory::query()
                ->whereIn('slug', ChecklistCategory::categoryScopedSlugs())
                ->pluck('id');

            $deactivated = ChecklistItem::query()
                ->where('scope', 'infrastructure')
                ->where('is_active', true)
                ->when(
                    $scopedCategoryIds->isNotEmpty(),
                    fn ($query) => $query->whereNotIn('checklist_category_id', $scopedCategoryIds),
                )
                ->update(['is_active' => false]);

            $this->info("Deactivated {$deactivated} legacy checklist item(s).");
        }

        return self::SUCCESS;
    }
}
