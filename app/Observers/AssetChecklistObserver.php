<?php

namespace App\Observers;

use App\Actions\SyncAssetChecklistAction;
use App\Models\Asset;
use App\Models\ChecklistItem;

class AssetChecklistObserver
{
    /**
     * Re-materialise the asset's checklist items whenever it is created or
     * updated, so toggling in_checklist or changing the category/name/location
     * keeps the checklist in sync.
     */
    public function saved(Asset $asset): void
    {
        app(SyncAssetChecklistAction::class)->execute($asset);
    }

    /**
     * Deactivate generated items before the asset is deleted. Grouped assets
     * share their items with same-name siblings, so those stay active while
     * the group still has members; the FK is nullOnDelete and the next sync
     * hands ownership to the remaining member.
     */
    public function deleting(Asset $asset): void
    {
        $sync = app(SyncAssetChecklistAction::class);

        $hasSiblings = $sync->isGrouped($asset)
            && $sync->groupMembers($asset)->contains(
                fn (Asset $member): bool => $member->id !== $asset->id,
            );

        if ($hasSiblings) {
            return;
        }

        ChecklistItem::query()
            ->where('asset_id', $asset->id)
            ->update(['is_active' => false]);
    }
}
