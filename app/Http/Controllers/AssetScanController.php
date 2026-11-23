<?php

namespace App\Http\Controllers;

use App\Helpers\AssetSheetHelper;
use App\Models\Asset;
use App\Models\ChecklistDayNote;
use App\Models\ChecklistEntry;
use App\Models\ChecklistItem;
use App\Models\ChecklistSlotNote;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AssetScanController extends Controller
{
    /**
     * The QR scanner page. Login only (no asset permission) so any
     * authenticated user can point a camera or upload a photo of a
     * sticker and land on the matching asset page.
     */
    public function index(): InertiaResponse
    {
        return Inertia::render('dynamic/assets/scan/index');
    }

    /**
     * The scan target page for one asset: identity fields, the sections
     * gated by the asset's scan flags (checklist and maintenance history),
     * and — for managers — the create-maintenance dialog with its asset
     * options.
     */
    public function show(Request $request, Asset $asset): InertiaResponse
    {
        // A camera sticker resolves to its device, which owns the checklist.
        $target = AssetSheetHelper::isCctvCamera($asset) && $asset->parent !== null
            ? $asset->parent
            : $asset;

        return $this->renderScan(
            $request,
            $target,
            AssetSheetHelper::assetGroupMembers($target),
        );
    }

    /**
     * The scan target for a grouped sticker (one QR per unit or device). UPS
     * groups are resolved by name; CCTV devices pass their category too so
     * a "CCTV-1 DVR" sticker never resolves to a different device type.
     */
    public function group(Request $request): InertiaResponse
    {
        $name = $request->string('name')->toString();
        // `category` is the current QR parameter; `category_id` keeps older
        // printed CCTV stickers working.
        $categoryId = $request->integer('category') ?: $request->integer('category_id');

        $members = $categoryId > 0
            ? AssetSheetHelper::assetGroupMembersByNameAndCategory($name, $categoryId)
            : AssetSheetHelper::assetGroupMembersByName($name);

        abort_if($members->isEmpty(), 404);

        return $this->renderScan($request, $members->first(), $members);
    }

    /**
     * Build the scan page payload for the scanned asset and the group it
     * belongs to.
     *
     * @param  Collection<int, Asset>  $members
     */
    private function renderScan(
        Request $request,
        Asset $asset,
        Collection $members,
    ): InertiaResponse {
        return Inertia::render('dynamic/assets/scan/show', [
            'asset' => [
                'id' => $asset->id,
                'name' => $asset->name,
                'display_name' => AssetSheetHelper::displayName($asset),
                // assets.category_id points at the (sub)category row; the
                // "Kategori" shown here must be its top-level parent.
                'category' => $asset->category?->parent?->name ?? $asset->category?->name,
                'brand' => $asset->brand,
                'type' => $asset->type,
                'model' => $asset->model,
                'serial_number' => $asset->serial_number,
                'capacity' => $asset->capacity,
                'manufacture_year' => $asset->manufacture_year,
                'operation_year' => $asset->operation_year,
                'quantity' => $asset->quantity,
                'floor' => $asset->floor,
                'room' => $asset->room,
                'in_checklist' => (bool) $asset->in_checklist,
                'in_maintenance' => (bool) $asset->in_maintenance,
            ],
            'members' => $members
                ->map(fn (Asset $member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'category' => $member->category?->name,
                    'brand' => $member->brand,
                    'model' => $member->model,
                    'serial_number' => $member->serial_number,
                    'identity' => AssetSheetHelper::assetDetailFields($member),
                ])
                ->values()
                ->all(),
            'maintenances' => $asset->maintenances()
                ->latest('maintenance_date')
                ->latest('id')
                ->get(['id', 'maintenance_date', 'end_date', 'status', 'result'])
                ->toArray(),
            'assetOptions' => Asset::query()->orderBy('name')->get(['id', 'name']),
            'checklist' => $this->checklistPayload($asset),
            'canManage' => $request->user()->hasPermissionTo('manage asset management'),
            'canManageChecklist' => $request->user()->checkPermissionTo('manage checklist'),
        ]);
    }

    /**
     * The real checklist configured for the scanned asset's group: active
     * asset items plus today's entries and day note. Null when the asset has
     * no configured items yet.
     *
     * @return array<string, mixed>|null
     */
    private function checklistPayload(Asset $asset): ?array
    {
        if (AssetSheetHelper::isCctvAsset($asset)) {
            $items = ChecklistItem::query()
                ->where('is_active', true)
                ->where('scope', 'asset')
                ->where('checklist_category_id', $asset->checklist_category_id)
                ->where('label', AssetSheetHelper::displayName($asset))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        } else {
            $items = ChecklistItem::query()
                ->where('is_active', true)
                ->where('scope', 'asset')
                ->where('group', $asset->name)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        // Category-scoped checklists (FAP panels) have no per-asset rows, so
        // fall back to the items configured on the asset's checklist
        // category. Scanning the panel then still shows the full checklist.
        if ($items->isEmpty() && $asset->checklistCategory?->usesCategoryItems()) {
            $items = ChecklistItem::query()
                ->where('is_active', true)
                ->where('checklist_category_id', $asset->checklist_category_id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        if ($items->isEmpty()) {
            return null;
        }

        $category = $items->first()->category;
        $date = today()->toDateString();
        $itemIds = $items->pluck('id')->all();

        $entries = ChecklistEntry::query()
            ->whereIn('checklist_item_id', $itemIds)
            ->whereDate('entry_date', $date)
            ->get(['checklist_item_id', 'time_slot', 'value', 'status'])
            ->map(fn (ChecklistEntry $entry): array => [
                'item_id' => $entry->checklist_item_id,
                'time_slot' => $entry->time_slot,
                'value' => $entry->value !== null ? (float) $entry->value : null,
                'status' => $entry->status,
            ])
            ->values()
            ->all();

        $dayNote = ChecklistDayNote::query()
            ->where('checklist_category_id', $category?->id)
            ->whereDate('entry_date', $date)
            ->first(['risk_level', 'note']);

        return [
            'date' => $date,
            'category' => [
                'id' => $category?->id,
                'name' => $category?->name,
                'time_slots' => $category?->time_slots ?? [],
            ],
            'items' => $items
                ->map(fn (ChecklistItem $item): array => [
                    'id' => $item->id,
                    'section' => $item->section,
                    'group' => $item->group,
                    'label' => $item->label,
                    'unit' => $item->unit,
                    'input_type' => $item->input_type,
                    'min_value' => $item->min_value !== null ? (float) $item->min_value : null,
                    'max_value' => $item->max_value !== null ? (float) $item->max_value : null,
                ])
                ->values()
                ->all(),
            'entries' => $entries,
            'slotNotes' => ChecklistSlotNote::query()
                ->where('checklist_category_id', $category?->id)
                ->whereDate('entry_date', $date)
                ->get(['time_slot', 'risk_level', 'note'])
                ->map(fn (ChecklistSlotNote $note): array => [
                    'time_slot' => $note->time_slot,
                    'risk_level' => $note->risk_level,
                    'note' => $note->note,
                ])
                ->values()
                ->all(),
            'dayNote' => [
                'risk_level' => $dayNote?->risk_level ?? 'LOW',
                'note' => $dayNote?->note,
            ],
        ];
    }
}
