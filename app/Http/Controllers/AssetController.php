<?php

namespace App\Http\Controllers;

use App\Events\AssetsChanged;
use App\Helpers\AssetSheetHelper;
use App\Http\Requests\AssetBulkRequest;
use App\Http\Requests\AssetRequest;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Menu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    /**
     * Whitelisted search columns: request value => database column.
     *
     * @var array<string, string>
     */
    private const SEARCH_COLUMNS = [
        'name' => 'name',
        'alias' => 'alias',
        'ip_address' => 'ip_address',
        'brand' => 'brand',
        'serial_number' => 'serial_number',
        'type' => 'type',
        'model' => 'model',
        'capacity' => 'capacity',
        'room' => 'room',
        'floor' => 'floor',
        'owner' => 'owner',
        'category' => 'category',
        'description' => 'description',
    ];

    /**
     * Redirect the module root to the asset overview page.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('assets.overview');
    }

    /**
     * Show the asset overview page (/assets/overview): summary cards only.
     * The cards stay empty until the summary phase is implemented.
     */
    public function overview(Request $request): InertiaResponse
    {
        $childMenu = Menu::where('url', '/'.$request->path())->first();

        return Inertia::render('dynamic/assets/overview', [
            ...$this->childMenuProps($childMenu?->parent, $childMenu),
            'summary' => [
                'total_assets' => null,
                'total_categories' => null,
                'total_maintenances' => null,
                'total_troubleshoots' => null,
            ],
            'canManage' => $request->user()->hasPermissionTo('manage asset management'),
        ]);
    }

    /**
     * Export the active sheet as a CSV file.
     */
    public function export(Request $request): StreamedResponse
    {
        $sheet = AssetSheetHelper::sheetKey($request->string('sheet')->toString());
        $columns = AssetSheetHelper::columns($sheet);
        $assets = $this->filteredQuery($request)->get();

        if ($sheet === 'cctv') {
            $assets->load('parent:id,name');
            $assets->loadCount('children');
        }

        $headers = array_column($columns, 'label');
        $nameById = Category::query()->pluck('name', 'id');
        $parentIdById = Category::query()->pluck('parent_id', 'id');

        return Response::streamDownload(function () use ($assets, $columns, $headers, $nameById, $parentIdById, $sheet) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, escape: '\\');

            foreach ($assets as $asset) {
                $categoryPath = AssetSheetHelper::categoryPath($asset, $nameById, $parentIdById);
                $values = AssetSheetHelper::values($asset, $columns, $categoryPath);

                if ($sheet === 'cctv') {
                    $values['perangkat'] = $asset->parent?->name ?? '';
                    $values['jumlah_kamera'] = (string) ($asset->children_count ?? 0);
                }

                fputcsv(
                    $handle,
                    array_values($values),
                    escape: '\\',
                );
            }

            fclose($handle);
        }, 'assets-'.$sheet.'-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Store a newly created asset.
     */
    public function store(AssetRequest $request): RedirectResponse
    {
        $sheet = AssetSheetHelper::sheetKey($request->validated('sheet'));
        $this->assertCategoryAllowed($request->validated(), $sheet);

        $attributes = $this->assetAttributes($request->validated(), $sheet);
        $existing = $this->findMatchingAsset($attributes);
        $updated = $existing !== null;

        if ($existing !== null) {
            if (AssetSheetHelper::isCctvDeviceCategory((int) $attributes['category_id'])) {
                $this->updateCctvDevice($existing, $attributes);
            } else {
                $existing->update($attributes);
            }
        } else {
            Asset::create($attributes);
        }

        AssetsChanged::dispatch();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $updated ? __('Asset updated.') : __('Asset created.'),
        ]);

        return $this->redirectToSheet($sheet);
    }

    /**
     * Store several assets that share the same device fields, one per camera
     * row submitted by a sheet with a multi-row create form. On the CCTV
     * sheet the device itself is stored once and every camera row becomes a
     * camera asset linked to it; a camera submitted on its own (category
     * Kamera plus a device parent) is stored as a single asset.
     */
    public function storeBulk(AssetBulkRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $sheet = AssetSheetHelper::sheetKey($validated['sheet'] ?? null);
        $this->assertCategoryAllowed($validated, $sheet);

        $multi = AssetSheetHelper::multiRow($sheet);
        $keys = $multi['keys'] ?? [];
        $detailKeys = AssetSheetHelper::detailKeys($sheet);

        $cameraRows = collect($validated['cameras'] ?? [])
            ->map(fn (array $row): array => array_intersect_key($row, array_flip($keys)))
            ->filter(fn (array $row): bool => collect($row)->contains(
                fn ($value): bool => trim((string) $value) !== '',
            ))
            ->values();

        if ($cameraRows->isEmpty()) {
            $cameraRows = collect([[]]);
        }

        $cameraCategoryId = $sheet === 'cctv'
            ? (AssetSheetHelper::cctvCameraCategoryIds()[0] ?? null)
            : null;

        $created = 0;
        $updatedDevice = false;
        $updatedCamera = false;

        DB::transaction(function () use ($cameraRows, $validated, $sheet, $detailKeys, $cameraCategoryId, &$created, &$updatedDevice, &$updatedCamera): void {
            $payload = $validated;
            unset($payload['cameras']);

            $attributes = $this->assetAttributes($payload, $sheet);

            // A camera submitted on its own (category Kamera, with a device
            // parent) is a single asset. Re-submitting the same fields updates
            // it instead of creating a duplicate.
            $isCamera = AssetSheetHelper::isCctvCameraCategory((int) $attributes['category_id']);

            if ($isCamera) {
                $existingCamera = $this->findMatchingAsset($attributes);

                if ($existingCamera !== null) {
                    $existingCamera->update($attributes);
                    $updatedCamera = true;

                    return;
                }

                $parent = Asset::query()->find((int) $attributes['parent_asset_id']);

                $camera = $this->cctvCameraAttributes(
                    $parent,
                    $attributes['details'] ?? [],
                    (int) $attributes['category_id'],
                    $attributes['notes'] ?? null,
                );

                Asset::create($camera);
                $created++;

                return;
            }

            // Only a DVR/NVR device is split into a device plus camera assets.
            // Every other sheet (and a camera-only category) keeps the old
            // one-asset-per-submitted-row behaviour.
            $splitDevice = $cameraCategoryId !== null
                && AssetSheetHelper::isCctvDeviceCategory((int) $attributes['category_id']);

            if (! $splitDevice) {
                foreach ($cameraRows as $row) {
                    $payload = $validated;
                    unset($payload['cameras']);

                    $payload['details'] = array_intersect_key($row, array_flip($detailKeys));

                    if (array_key_exists('catatan', $row)) {
                        $payload['notes'] = $row['catatan'];
                    }

                    Asset::create($this->assetAttributes($payload, $sheet));
                    $created++;
                }

                return;
            }

            $existing = $this->findCctvDeviceByIdentity($attributes);

            if ($existing !== null) {
                $device = $this->updateCctvDevice($existing, $attributes);
                $updatedDevice = true;
            } else {
                $device = Asset::create($attributes);
                $created++;
            }

            foreach ($cameraRows as $row) {
                $details = array_intersect_key($row, array_flip($detailKeys));

                if (collect($details)->every(fn ($value): bool => trim((string) $value) === '')) {
                    continue;
                }

                Asset::create($this->cctvCameraAttributes(
                    $device,
                    $this->normalizeCctvCameraDetails($details),
                    $cameraCategoryId,
                    $row['catatan'] ?? null,
                ));
                $created++;
            }
        });

        AssetsChanged::dispatch();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => match (true) {
                $updatedDevice => __('Perangkat CCTV diperbarui, :count kamera baru dibuat.', ['count' => $created]),
                $updatedCamera => __('Aset diperbarui.'),
                default => __(':count aset dibuat.', ['count' => $created]),
            },
        ]);

        return $this->redirectToSheet($sheet);
    }

    /**
     * Update the given asset.
     */
    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $sheet = AssetSheetHelper::sheetKey($request->validated('sheet'));
        $this->assertCategoryAllowed($request->validated(), $sheet);

        $asset->update($this->assetAttributes($request->validated(), $sheet, $asset));

        // A device's shared fields also describe its camera channels, so keep
        // the copied values in sync after the device itself is edited.
        if (AssetSheetHelper::isCctvDeviceCategory((int) $asset->category_id)) {
            $asset->children()->update([
                'brand' => $asset->brand,
                'alias' => $asset->alias,
                'ip_address' => $asset->ip_address,
                'type' => $asset->type,
                'model' => $asset->model,
                'serial_number' => $asset->serial_number,
                'manufacture_year' => $asset->manufacture_year,
                'operation_year' => $asset->operation_year,
                'quantity' => $asset->quantity,
                'floor' => $asset->floor,
                'room' => $asset->room,
            ]);
        }

        AssetsChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Asset updated.')]);

        return $this->redirectToSheet($sheet);
    }

    /**
     * Delete the given asset.
     */
    public function destroy(Request $request, Asset $asset): RedirectResponse
    {
        $sheet = AssetSheetHelper::sheetKey($request->string('sheet')->toString());

        $referenced = Customer::query()
            ->where('panel_asset_id', $asset->id)
            ->orWhere('panel_asset_id_2', $asset->id)
            ->exists();

        if ($referenced) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Asset is assigned to a customer and cannot be deleted.'),
            ]);

            return $this->redirectToSheet($sheet);
        }

        if ($asset->maintenances()->exists() || $asset->troubleshoots()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Asset has maintenance or trouble records and cannot be deleted.'),
            ]);

            return $this->redirectToSheet($sheet);
        }

        // Camera channels are part of their CCTV device, so removing the
        // device removes them too.
        if (AssetSheetHelper::isCctvDeviceCategory((int) $asset->category_id)) {
            $asset->children()->get()->each(fn (Asset $child) => $child->delete());
        }

        $asset->delete();

        AssetsChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Asset deleted.')]);

        return $this->redirectToSheet($sheet);
    }

    /**
     * Build a camera asset for a CCTV device. The camera keeps a copy of the
     * device fields for context and stores the per-camera values in details.
     *
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    /**
     * Build a camera asset for a CCTV device. The camera keeps a copy of the
     * device fields for context and stores the per-camera values in details.
     *
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function cctvCameraAttributes(
        Asset $device,
        array $details,
        int $cameraCategoryId,
        ?string $notes = null,
    ): array {
        return [
            'name' => AssetSheetHelper::cctvCameraName($details, $device->name),
            'category_id' => $cameraCategoryId,
            'parent_asset_id' => $device->id,
            'alias' => $device->alias,
            'ip_address' => $device->ip_address,
            'owner' => $device->owner,
            'brand' => $device->brand,
            'type' => $device->type,
            'model' => $device->model,
            'serial_number' => $device->serial_number,
            'manufacture_year' => $device->manufacture_year,
            'operation_year' => $device->operation_year,
            'quantity' => $device->quantity,
            'floor' => $device->floor,
            'room' => $device->room,
            'notes' => $notes,
            'details' => $details,
            'in_checklist' => false,
            'in_maintenance' => false,
            'checklist_category_id' => null,
        ];
    }

    /**
     * Find an existing asset whose submitted fields match exactly. CCTV
     * devices keep their six-field identity so capacities can be merged
     * instead of compared.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function findMatchingAsset(array $attributes): ?Asset
    {
        $categoryId = (int) ($attributes['category_id'] ?? 0);

        if ($categoryId === 0) {
            return null;
        }

        if (AssetSheetHelper::isCctvDeviceCategory($categoryId)) {
            return $this->findCctvDeviceByIdentity($attributes);
        }

        $name = trim((string) ($attributes['name'] ?? ''));

        $query = Asset::query()
            ->where('category_id', $categoryId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);

        if (array_key_exists('parent_asset_id', $attributes)) {
            $query->where('parent_asset_id', $attributes['parent_asset_id']);
        }

        return $query
            ->get()
            ->first(fn (Asset $asset): bool => $this->assetMatchesAttributes($asset, $attributes));
    }

    /**
     * Whether every submitted base and detail field equals the stored asset.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function assetMatchesAttributes(Asset $asset, array $attributes): bool
    {
        $stored = $asset->getAttributes();

        foreach ($attributes as $key => $value) {
            if ($key === 'details' || ! array_key_exists($key, $stored)) {
                continue;
            }

            if ($this->normalizedValue($asset->getAttribute($key)) !== $this->normalizedValue($value)) {
                return false;
            }
        }

        $submittedDetails = is_array($attributes['details'] ?? null) ? $attributes['details'] : [];
        $storedDetails = is_array($asset->details) ? $asset->details : [];

        foreach ($submittedDetails as $key => $value) {
            if ($this->normalizedValue($storedDetails[$key] ?? null) !== $this->normalizedValue($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compare values as trimmed strings so null and "" are equal.
     */
    private function normalizedValue(mixed $value): string
    {
        return trim((string) $value);
    }

    /**
     * Find an existing CCTV device with the same physical identity (name,
     * category, brand, IP, serial number, model).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function findCctvDeviceByIdentity(array $attributes): ?Asset
    {
        $name = mb_strtolower(trim((string) ($attributes['name'] ?? '')));
        $categoryId = (int) ($attributes['category_id'] ?? 0);
        $key = AssetSheetHelper::cctvIdentityKeyFromValues($attributes);

        return Asset::query()
            ->where('category_id', $categoryId)
            ->whereNull('parent_asset_id')
            ->whereRaw('LOWER(name) = ?', [$name])
            ->get()
            ->first(fn (Asset $asset): bool => AssetSheetHelper::cctvDeviceIdentityKey($asset) === $key);
    }

    /**
     * Update the existing device from the create form. Shared fields are
     * refreshed, HDD capacities are merged, and checklist/QR settings stay
     * untouched.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function updateCctvDevice(Asset $device, array $attributes): Asset
    {
        $updates = [];

        foreach ([
            'name',
            'alias',
            'owner',
            'brand',
            'type',
            'model',
            'serial_number',
            'manufacture_year',
            'operation_year',
            'quantity',
            'floor',
            'room',
            'ip_address',
            'notes',
        ] as $field) {
            if (array_key_exists($field, $attributes)) {
                $updates[$field] = $attributes[$field];
            }
        }

        $updates['capacity'] = AssetSheetHelper::mergeCapacityValues([
            $device->capacity,
            $attributes['capacity'] ?? null,
        ]);

        $device->update($updates);

        return $device->fresh();
    }

    /**
     * Normalise the floor:count lists stored in the camera details, using the
     * same rules as the device form.
     *
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function normalizeCctvCameraDetails(array $details): array
    {
        foreach (AssetSheetHelper::countKeys('cctv') as $key) {
            try {
                $details[$key] = AssetSheetHelper::normalizeFloorCounts($details[$key] ?? null);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages([
                    'cameras' => __('Format lantai:jumlah tidak valid. Contoh: 2:5, 3:3.'),
                ]);
            }
        }

        return $details;
    }

    /**
     * Build the asset query filtered by the request sheet and search.
     */
    private function filteredQuery(Request $request): Builder
    {
        $sheet = AssetSheetHelper::sheetKey($request->string('sheet')->toString());
        $search = $request->string('search')->toString();

        $query = Asset::query();

        AssetSheetHelper::filterBySheet($query, $sheet);

        return $query
            ->when($search !== '', function (Builder $query) use ($search, $request) {
                $column = $this->resolveSearchColumn($request);

                if ($column === 'all') {
                    $query->where(function (Builder $query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('alias', 'like', "%{$search}%")
                            ->orWhere('ip_address', 'like', "%{$search}%")
                            ->orWhere('brand', 'like', "%{$search}%")
                            ->orWhere('serial_number', 'like', "%{$search}%")
                            ->orWhere('type', 'like', "%{$search}%")
                            ->orWhere('model', 'like', "%{$search}%")
                            ->orWhere('capacity', 'like', "%{$search}%")
                            ->orWhere('room', 'like', "%{$search}%")
                            ->orWhere('floor', 'like', "%{$search}%")
                            ->orWhere('owner', 'like', "%{$search}%")
                            ->orWhereIn('category_id', $this->matchingCategoryIds($search));
                    });

                    return;
                }

                if ($column === 'category') {
                    $query->whereIn('category_id', $this->matchingCategoryIds($search));

                    return;
                }

                if ($column === 'description') {
                    $query->where(function (Builder $query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhereIn('category_id', $this->matchingCategoryIds($search));
                    });

                    return;
                }

                $query->where(self::SEARCH_COLUMNS[$column], 'like', "%{$search}%");
            })
            ->orderBy('name');
    }

    /**
     * Resolve the requested search column against the whitelist.
     */
    private function resolveSearchColumn(Request $request): string
    {
        $column = $request->string('search_column')->toString();

        if ($column === '' || $column === 'all') {
            return 'all';
        }

        return isset(self::SEARCH_COLUMNS[$column]) ? $column : 'all';
    }

    /**
     * Find category ids whose name matches plus all of their descendants.
     *
     * @return list<int>
     */
    private function matchingCategoryIds(string $search): array
    {
        return AssetSheetHelper::categoryIdsMatching($search);
    }

    /**
     * Resolve the submitted category path names into ids and keep only the
     * detail keys that belong to the selected sheet.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function assetAttributes(array $validated, string $sheet, ?Asset $asset = null): array
    {
        $details = $validated['details'] ?? [];
        $allowedDetailKeys = AssetSheetHelper::detailKeys($sheet);

        $details = array_intersect_key($details, array_flip($allowedDetailKeys));

        foreach (AssetSheetHelper::countKeys($sheet) as $key) {
            try {
                $details[$key] = AssetSheetHelper::normalizeFloorCounts($details[$key] ?? null);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages([
                    'details.'.$key => __('Format lantai:jumlah tidak valid. Contoh: 2:5, 3:3.'),
                ]);
            }
        }

        $validated['details'] = $details;

        $path = array_values(array_filter(
            array_map('trim', $validated['category_path']),
            fn (string $name) => $name !== '',
        ));

        $node = null;

        foreach ($path as $name) {
            $node = $this->resolveCategoryNode($name, $node);
        }

        $validated['category_id'] = $node->id;

        $validated = $this->applyBatteryParent($validated, $sheet, $path, $asset);
        $validated = $this->applyCctvParent($validated, $asset);

        // Capacity can be entered one slot per line; store it on one line.
        if (array_key_exists('capacity', $validated)) {
            $capacity = AssetSheetHelper::normalizeCapacityValue($validated['capacity']);
            $validated['capacity'] = $capacity !== '' ? $capacity : null;
        }

        unset($validated['category_path'], $validated['sheet']);

        return $validated;
    }

    /**
     * Battery assets point at their UPS through parent_asset_id. The parent
     * owns the name, brand, and capacity shown in the battery table, so those
     * values are copied over and a parent is required for battery categories.
     *
     * @param  array<string, mixed>  $validated
     * @param  list<string>  $path
     * @return array<string, mixed>
     */
    private function applyBatteryParent(array $validated, string $sheet, array $path, ?Asset $asset): array
    {
        if (! AssetSheetHelper::pathIsBattery($sheet, $path)) {
            // A camera keeps its device link: the submitted parent wins, then
            // the existing link. Everything else non-battery has no parent.
            $validated['parent_asset_id'] = AssetSheetHelper::isCctvCameraCategory(
                (int) $validated['category_id'],
            )
                ? ($validated['parent_asset_id'] ?? $asset?->parent_asset_id)
                : null;

            return $validated;
        }

        $parentId = $validated['parent_asset_id'] ?? null;

        if ($parentId === null) {
            throw ValidationException::withMessages([
                'parent_asset_id' => __('Pilih UPS induk untuk aset baterai.'),
            ]);
        }

        if ($asset !== null && (int) $parentId === $asset->id) {
            throw ValidationException::withMessages([
                'parent_asset_id' => __('Aset tidak boleh menjadi induk dirinya sendiri.'),
            ]);
        }

        $parent = Asset::query()->with('category')->find($parentId);
        $batteryCategoryIds = AssetSheetHelper::batteryCategoryIds($sheet);

        if ($parent === null
            || in_array($parent->category_id, $batteryCategoryIds, true)) {
            throw ValidationException::withMessages([
                'parent_asset_id' => __('UPS induk tidak valid.'),
            ]);
        }

        $sheetCategoryIds = AssetSheetHelper::categoryIds($sheet);

        if ($sheetCategoryIds !== [] && ! in_array($parent->category_id, $sheetCategoryIds, true)) {
            throw ValidationException::withMessages([
                'parent_asset_id' => __('UPS induk tidak valid.'),
            ]);
        }

        $validated['parent_asset_id'] = $parent->id;
        $validated['name'] = $parent->name;
        $validated['brand'] = $parent->brand;
        $validated['capacity'] = $parent->capacity;

        return $validated;
    }

    /**
     * Camera assets must point at a DVR/NVR device. A camera can be added on
     * its own (category Kamera plus a parent) or edited without losing the
     * link to its device.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applyCctvParent(array $validated, ?Asset $asset): array
    {
        if (! AssetSheetHelper::isCctvCameraCategory((int) ($validated['category_id'] ?? 0))) {
            return $validated;
        }

        $parentId = $validated['parent_asset_id'] ?? null;

        if ($parentId === null) {
            throw ValidationException::withMessages([
                'parent_asset_id' => __('Pilih perangkat induk untuk kamera.'),
            ]);
        }

        if ($asset !== null && (int) $parentId === $asset->id) {
            throw ValidationException::withMessages([
                'parent_asset_id' => __('Aset tidak boleh menjadi induk dirinya sendiri.'),
            ]);
        }

        $parent = Asset::query()->find($parentId);
        $deviceCategoryIds = array_values(array_diff(
            AssetSheetHelper::categoryIds('cctv'),
            AssetSheetHelper::cctvCameraCategoryIds(),
        ));

        if ($parent === null
            || $parent->parent_asset_id !== null
            || ! in_array($parent->category_id, $deviceCategoryIds, true)) {
            throw ValidationException::withMessages([
                'parent_asset_id' => __('Perangkat induk tidak valid.'),
            ]);
        }

        $validated['parent_asset_id'] = $parent->id;
        // The camera asset name follows its alias/channel, then falls back to
        // the existing name (edit) or the device name (create).
        $details = is_array($validated['details'] ?? null) ? $validated['details'] : [];
        $validated['name'] = AssetSheetHelper::cctvCameraName(
            $details,
            $asset?->name ?? $parent->name,
        );
        // A camera never owns a checklist row or HDD capacity; both belong
        // to the device.
        $validated['in_checklist'] = false;
        $validated['in_maintenance'] = false;
        $validated['checklist_category_id'] = null;
        $validated['capacity'] = null;

        return $validated;
    }

    /**
     * Ensure the submitted root category belongs to the selected sheet.
     *
     * @param  array<string, mixed>  $validated
     */
    private function assertCategoryAllowed(array $validated, string $sheet): void
    {
        $root = $validated['category_path'][0] ?? '';

        if (! AssetSheetHelper::categoryAllowed($sheet, $root)) {
            throw ValidationException::withMessages([
                'category_path.0' => __('Kategori tidak sesuai dengan sheet yang dipilih.'),
            ]);
        }

        if ($sheet === AssetSheetHelper::config()['default']
            && AssetSheetHelper::categoryManagedByOtherSheet($root)) {
            throw ValidationException::withMessages([
                'category_path.0' => __('Kategori ini dikelola di sheet :sheet.', [
                    'sheet' => AssetSheetHelper::managedSheetLabelForCategoryName($root),
                ]),
            ]);
        }
    }

    /**
     * Redirect back to the active sheet.
     */
    private function redirectToSheet(string $sheet): RedirectResponse
    {
        return $sheet === AssetSheetHelper::config()['default']
            ? to_route('assets.list')
            : to_route('assets.list', ['sheet' => $sheet]);
    }

    /**
     * Find the category node by case-insensitive name under the parent or create it.
     */
    private function resolveCategoryNode(string $name, ?Category $parent): Category
    {
        $name = trim($name);

        return Category::query()
            ->when(
                $parent === null,
                fn ($query) => $query->whereNull('parent_id'),
                fn ($query) => $query->where('parent_id', $parent->id),
            )
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first()
            ?? Category::create(['parent_id' => $parent?->id, 'name' => $name]);
    }
}
