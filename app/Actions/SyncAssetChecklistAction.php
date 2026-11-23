<?php

namespace App\Actions;

use App\Helpers\AssetSheetHelper;
use App\Models\Asset;
use App\Models\ChecklistCategory;
use App\Models\ChecklistItem;
use App\Models\ChecklistItemTemplate;
use Illuminate\Support\Collection;

class SyncAssetChecklistAction
{
    /**
     * Materialise the asset's checklist items from the template of its
     * top-level (root) category, filed under the checklist category chosen
     * in Asset Settings. Items that no longer match a template are
     * deactivated instead of deleted so their recorded entries survive.
     *
     * Assets in the UPS sheet share a single checklist per name: the whole
     * group feeds one set of items so the daily check does not show the same
     * readings once per physical member (frame, module, battery, ...).
     */
    public function execute(Asset $asset): void
    {
        $asset->loadMissing('category');

        // Camera channels are part of their device and never own a checklist
        // row; the device materialises the single shared status row.
        if (AssetSheetHelper::isCctvCamera($asset)) {
            return;
        }

        $assetChecklistCategory = $asset->checklistCategory()->first();

        // CCTV is one checklist row per device (name + category), shared by
        // all camera channels on that device.
        if ($assetChecklistCategory?->slug === 'cctv') {
            $this->syncCctv($assetChecklistCategory);

            return;
        }

        $grouped = $this->isGrouped($asset);
        $members = $this->groupMembers($asset);

        $owner = $this->resolveOwner($members);
        $rootCategory = $owner?->category?->root();
        $checklistCategory = $owner?->checklistCategory()->first();

        // FAP panels own their checklist at category level, so assigning an
        // asset to those categories must not duplicate the rows per asset.
        if ($checklistCategory?->usesCategoryItems()) {
            $this->deactivate($members);

            return;
        }

        if ($owner === null || $rootCategory === null || $checklistCategory === null) {
            $this->deactivate($members);

            return;
        }

        $templates = ChecklistItemTemplate::query()
            ->where('category_id', $rootCategory->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($templates->isEmpty()) {
            $this->deactivate($members);

            return;
        }

        $section = $this->sectionFor($owner, $rootCategory->name);
        $keptIds = [];

        foreach ($templates as $template) {
            $key = [
                'section' => $template->section ?? $section,
                'group' => $template->group ?? $owner->name,
                'label' => $template->label,
            ];

            // Grouped assets share one row per item; the owning asset is
            // reassigned as the group's representative changes.
            if (! $grouped) {
                $key = ['asset_id' => $owner->id, ...$key];
            }

            $item = ChecklistItem::updateOrCreate($key, [
                'asset_id' => $owner->id,
                'checklist_category_id' => $checklistCategory->id,
                'scope' => 'asset',
                'unit' => $template->unit,
                'input_type' => $template->input_type,
                'min_value' => $template->min_value,
                'max_value' => $template->max_value,
                'sort_order' => $template->sort_order,
                'is_active' => true,
            ]);

            $keptIds[] = $item->id;
        }

        $this->deactivate($members, $keptIds);
    }

    /**
     * Rebuild the CCTV checklist: one status row per assigned device,
     * labelled with the device name and category (CCTV-1 DVR). Every row is
     * shared by the channels of that device.
     */
    private function syncCctv(ChecklistCategory $checklistCategory): void
    {
        $cameraCategoryIds = AssetSheetHelper::cctvCameraCategoryIds();
        $deviceCategoryIds = array_values(array_diff(
            AssetSheetHelper::categoryIds('cctv'),
            $cameraCategoryIds,
        ));

        $devices = Asset::query()
            ->with('category')
            ->where('checklist_category_id', $checklistCategory->id)
            ->where('in_checklist', true)
            ->whereIn('category_id', $deviceCategoryIds)
            ->whereNull('parent_asset_id')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Asset $asset): string => mb_strtolower(trim($asset->name)).'|'.$asset->category_id);

        $keptIds = [];
        $sortOrder = 0;

        foreach ($devices as $members) {
            $owner = $members->first();

            $item = ChecklistItem::updateOrCreate(
                [
                    'checklist_category_id' => $checklistCategory->id,
                    'scope' => 'asset',
                    'section' => 'CCTV',
                    'group' => null,
                    'label' => AssetSheetHelper::displayName($owner),
                ],
                [
                    'asset_id' => $owner->id,
                    'unit' => null,
                    'input_type' => 'status',
                    'min_value' => null,
                    'max_value' => null,
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                ],
            );

            $keptIds[] = $item->id;
        }

        // Retire the old category-scoped rows and any per-channel leftovers.
        ChecklistItem::query()
            ->where('checklist_category_id', $checklistCategory->id)
            ->whereNotIn('id', $keptIds)
            ->update(['is_active' => false]);
    }

    /**
     * Whether the asset belongs to a sheet that collapses same-name assets
     * into one settings row (UPS, battery, and CCTV devices).
     */
    public function isGrouped(Asset $asset): bool
    {
        return AssetSheetHelper::isGroupedAsset($asset)
            || AssetSheetHelper::isCctvAsset($asset);
    }

    /**
     * Every asset that shares the checklist identity of the given asset.
     * Non-grouped assets are their own group.
     *
     * @return Collection<int, Asset>
     */
    public function groupMembers(Asset $asset): Collection
    {
        return AssetSheetHelper::assetGroupMembers($asset);
    }

    /**
     * Pick the group member that owns the shared items. The lowest id keeps
     * the choice deterministic, and the items move to the next member when
     * this one leaves the checklist.
     *
     * @param  Collection<int, Asset>  $members
     */
    private function resolveOwner(Collection $members): ?Asset
    {
        return $members->first(fn (Asset $member): bool => $member->in_checklist
            && $member->checklist_category_id !== null);
    }

    /**
     * Deactivate the generated items of the given members, keeping the ones
     * that still match a template so their entries stay visible.
     *
     * @param  Collection<int, Asset>  $members
     * @param  list<int>  $keptIds
     */
    private function deactivate(Collection $members, array $keptIds = []): void
    {
        $query = ChecklistItem::query()
            ->whereIn('asset_id', $members->pluck('id')->all());

        if ($keptIds !== []) {
            $query->whereNotIn('id', $keptIds);
        }

        $query->update(['is_active' => false]);
    }

    /**
     * Build the display section from the asset location, falling back to
     * the category name when floor and room are empty.
     */
    private function sectionFor(Asset $asset, string $fallback): string
    {
        $parts = [];

        $floor = trim((string) $asset->floor);

        if ($floor !== '' && $floor !== '-') {
            $parts[] = 'Lantai '.$floor;
        }

        $room = trim((string) $asset->room);

        if ($room !== '' && $room !== '-') {
            $parts[] = $room;
        }

        return $parts === [] ? $fallback : implode(' - ', $parts);
    }
}
