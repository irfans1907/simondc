<?php

namespace App\Http\Controllers;

use App\Events\AssetsChanged;
use App\Models\Asset;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\PanelMcb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PanelPowerController extends Controller
{
    /**
     * Show the panel power list page (/assets/panelpowerlist). The panels are
     * regular assets in the "Panel Power" category (+ descendants), managed
     * through Asset Management; this page only manages their MCB slots.
     */
    public function index(Request $request): InertiaResponse
    {
        $canManage = $request->user()->hasPermissionTo('manage asset management');

        $childMenu = Menu::where('url', '/'.$request->path())->first();

        return Inertia::render('dynamic/assets/panelpowerlist', [
            ...$this->childMenuProps($childMenu?->parent, $childMenu),
            'panels' => $this->panelPowerAssets()
                ->with('panelMcbs')
                ->orderBy('name')
                ->get(['id', 'name', 'floor', 'room', 'capacity'])
                ->map(fn (Asset $asset) => [
                    'id' => $asset->id,
                    'name' => $asset->name,
                    'floor' => $asset->floor,
                    'room' => $asset->room,
                    'capacity' => $asset->capacity,
                    'panel_mcbs' => $asset->panelMcbs
                        ->sortBy('mcb_number', SORT_NATURAL | SORT_FLAG_CASE)
                        ->values(),
                ]),
            'canManage' => $canManage,
        ]);
    }

    /**
     * Add an MCB slot to the given panel asset.
     */
    public function storeMcb(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($this->isPanelPowerAsset($asset), 404);

        $data = $request->validate([
            'mcb_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('panel_mcbs')->where('asset_id', $asset->id),
            ],
        ]);

        $asset->panelMcbs()->create($data);

        AssetsChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('MCB added.')]);

        return to_route('assets.panelpowerlist');
    }

    /**
     * Add several MCB slots to the given panel asset in one request. The
     * numbers continue from the panel's highest existing trailing-digit
     * number, keeping that number's prefix and padding (an empty panel
     * starts at MCB-01 with 2-digit padding), so they can never collide
     * with the unique(asset_id, mcb_number) index.
     */
    public function storeMcbs(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($this->isPanelPowerAsset($asset), 404);

        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $numbers = $this->nextMcbNumbers($asset, $data['count']);

        DB::transaction(function () use ($asset, $numbers) {
            $asset->panelMcbs()->createMany(
                array_map(fn (string $number) => ['mcb_number' => $number], $numbers),
            );
        });

        AssetsChanged::dispatch();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':count MCB added.', ['count' => count($numbers)]),
        ]);

        return to_route('assets.panelpowerlist');
    }

    /**
     * Update the given MCB slot of the given panel asset.
     */
    public function updateMcb(Request $request, Asset $asset, PanelMcb $panelMcb): RedirectResponse
    {
        abort_unless($this->isPanelPowerAsset($asset), 404);
        abort_unless($panelMcb->asset_id === $asset->id, 404);

        $data = $request->validate([
            'mcb_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('panel_mcbs')->where('asset_id', $asset->id)->ignore($panelMcb->id),
            ],
        ]);

        $panelMcb->update($data);

        AssetsChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('MCB updated.')]);

        return to_route('assets.panelpowerlist');
    }

    /**
     * Delete the given MCB slot of the given panel asset.
     */
    public function destroyMcb(Asset $asset, PanelMcb $panelMcb): RedirectResponse
    {
        abort_unless($this->isPanelPowerAsset($asset), 404);
        abort_unless($panelMcb->asset_id === $asset->id, 404);

        $referenced = Customer::query()
            ->where('panel_mcb_id', $panelMcb->id)
            ->orWhere('panel_mcb_id_2', $panelMcb->id)
            ->exists();

        if ($referenced) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('MCB is assigned to a customer and cannot be deleted.'),
            ]);

            return back();
        }

        $panelMcb->delete();

        AssetsChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('MCB deleted.')]);

        return to_route('assets.panelpowerlist');
    }

    /**
     * The assets that count as PDU panels: category "Panel Power" or any of
     * its descendants.
     */
    private function panelPowerAssets()
    {
        return Asset::query()
            ->whereIn('category_id', $this->categoryIdsByName('Panel Power'));
    }

    /**
     * Determine whether the asset lives in the Panel Power category tree.
     */
    private function isPanelPowerAsset(Asset $asset): bool
    {
        return in_array($asset->category_id, $this->categoryIdsByName('Panel Power'), true);
    }

    /**
     * Generate $count sequential MCB numbers continuing from the panel's
     * highest existing trailing-digit number, keeping that number's prefix
     * and a zero-pad of at least 2 digits. Numbers that do not end in
     * digits (e.g. "spare") are ignored when finding the highest.
     *
     * @return list<string>
     */
    private function nextMcbNumbers(Asset $asset, int $count): array
    {
        $prefix = 'MCB-';
        $width = 2;
        $next = 1;

        foreach ($asset->panelMcbs()->pluck('mcb_number') as $existing) {
            if (preg_match('/^(.*?)(\d+)$/', $existing, $matches) !== 1) {
                continue;
            }

            $value = (int) $matches[2];

            if ($value >= $next) {
                $next = $value + 1;
                $prefix = $matches[1] === '' ? 'MCB-' : $matches[1];
                $width = max(2, strlen($matches[2]));
            }
        }

        return array_map(
            fn (int $offset) => $prefix.str_pad((string) ($next + $offset), $width, '0', STR_PAD_LEFT),
            range(0, $count - 1),
        );
    }
}
