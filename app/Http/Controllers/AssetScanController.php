<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;
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
     * gated by the asset's scan flags (checklist placeholder and
     * maintenance history), and — for managers — the create-maintenance
     * dialog with its asset options.
     */
    public function show(Request $request, Asset $asset): InertiaResponse
    {
        return Inertia::render('dynamic/assets/scan/show', [
            'asset' => [
                'id' => $asset->id,
                'name' => $asset->name,
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
            'maintenances' => $asset->maintenances()
                ->latest('maintenance_date')
                ->latest('id')
                ->get(['id', 'maintenance_date', 'end_date', 'status', 'result'])
                ->toArray(),
            'assetOptions' => Asset::query()->orderBy('name')->get(['id', 'name']),
            'canManage' => $request->user()->hasPermissionTo('manage asset management'),
        ]);
    }
}
