<?php

namespace App\Http\Controllers;

use App\Events\AssetsChanged;
use App\Http\Requests\AssetRequest;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Menu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
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
        'brand' => 'brand',
        'serial_number' => 'serial_number',
        'type' => 'type',
        'model' => 'model',
        'capacity' => 'capacity',
        'room' => 'room',
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
     * Show the asset overview page (/assets/overview renders the asset list).
     */
    public function overview(Request $request): InertiaResponse
    {
        return $this->renderIndex($request, 'dynamic/assets/overview');
    }

    /**
     * Render the asset list under the given Inertia component.
     */
    private function renderIndex(Request $request, string $component): InertiaResponse
    {
        $perPage = $request->integer('per_page', 10);

        $assets = $this->filteredQuery($request)
            ->paginate($perPage)
            ->withQueryString();

        $nameById = Category::query()->pluck('name', 'id');
        $parentIdById = Category::query()->pluck('parent_id', 'id');

        $assets->through(function (Asset $asset) use ($nameById, $parentIdById) {
            return $asset->setAttribute(
                'category_path',
                $this->categoryPath($asset, $nameById, $parentIdById),
            );
        });

        $childMenu = Menu::where('url', '/'.$request->path())->first();

        return Inertia::render($component, [
            ...$this->childMenuProps($childMenu?->parent, $childMenu),
            'assets' => $assets,
            'categories' => $this->categoryTree(),
            'canManage' => $request->user()->hasPermissionTo('manage asset management'),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'search_column' => $this->resolveSearchColumn($request),
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Export the filtered assets as a CSV file.
     */
    public function export(Request $request): StreamedResponse
    {
        $nameById = Category::query()->pluck('name', 'id');
        $parentIdById = Category::query()->pluck('parent_id', 'id');

        $assets = $this->filteredQuery($request)->get();

        $headers = [
            'No', 'Nama Perangkat', 'Kategori', 'Sub Kategori', 'Deskripsi',
            'Merek', 'Type', 'Model', 'Serial Number', 'Kapasitas',
            'Tahun Manufaktur', 'Tahun Operasi', 'Jumlah', 'Lantai', 'Ruang', 'Catatan',
        ];

        return Response::streamDownload(function () use ($assets, $nameById, $parentIdById, $headers) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, escape: '\\');

            $row = 0;

            foreach ($assets as $asset) {
                $path = $this->categoryPath($asset, $nameById, $parentIdById);

                fputcsv($handle, [
                    ++$row,
                    $asset->name,
                    $path[0] ?? '',
                    implode(' > ', array_slice($path, 1)),
                    implode(' > ', $path),
                    $asset->brand,
                    $asset->type,
                    $asset->model,
                    $asset->serial_number,
                    $asset->capacity,
                    $asset->manufacture_year,
                    $asset->operation_year,
                    $asset->quantity,
                    $asset->floor,
                    $asset->room,
                    $asset->notes,
                ], escape: '\\');
            }

            fclose($handle);
        }, 'assets-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Store a newly created asset.
     */
    public function store(AssetRequest $request): RedirectResponse
    {
        Asset::create($this->assetAttributes($request->validated()));

        AssetsChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Asset created.')]);

        return to_route('assets.index');
    }

    /**
     * Update the given asset.
     */
    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $asset->update($this->assetAttributes($request->validated()));

        AssetsChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Asset updated.')]);

        return to_route('assets.index');
    }

    /**
     * Delete the given asset.
     */
    public function destroy(Asset $asset): RedirectResponse
    {
        $referenced = Customer::query()
            ->where('panel_asset_id', $asset->id)
            ->orWhere('panel_asset_id_2', $asset->id)
            ->exists();

        if ($referenced) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Asset is assigned to a customer and cannot be deleted.'),
            ]);

            return back();
        }

        if ($asset->maintenances()->exists() || $asset->troubleshoots()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Asset has maintenance or trouble records and cannot be deleted.'),
            ]);

            return back();
        }

        $asset->delete();

        AssetsChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Asset deleted.')]);

        return to_route('assets.index');
    }

    /**
     * Build the asset query filtered by the request search parameters.
     */
    private function filteredQuery(Request $request): Builder
    {
        $search = $request->string('search')->toString();

        return Asset::query()
            ->when($search !== '', function (Builder $query) use ($search, $request) {
                $column = $this->resolveSearchColumn($request);

                if ($column === 'all') {
                    $query->where(function (Builder $query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('brand', 'like', "%{$search}%")
                            ->orWhere('serial_number', 'like', "%{$search}%")
                            ->orWhere('type', 'like', "%{$search}%")
                            ->orWhere('model', 'like', "%{$search}%")
                            ->orWhere('capacity', 'like', "%{$search}%")
                            ->orWhere('room', 'like', "%{$search}%")
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
        $categories = Category::query()->get(['id', 'name', 'parent_id']);

        $childrenByParent = $categories
            ->groupBy(fn (Category $category) => $category->parent_id ?? 0);

        $matched = $categories
            ->filter(fn (Category $category) => mb_stripos($category->name, $search) !== false)
            ->pluck('id');

        $ids = $matched->all();

        $collectDescendants = function (int $parentId) use (&$collectDescendants, $childrenByParent, &$ids) {
            foreach ($childrenByParent->get($parentId, collect()) as $child) {
                $ids[] = $child->id;
                $collectDescendants($child->id);
            }
        };

        foreach ($matched as $id) {
            $collectDescendants($id);
        }

        return array_values(array_unique($ids));
    }

    /**
     * Resolve the full category path names for an asset.
     *
     * @param  Collection  $nameById
     * @param  Collection  $parentIdById
     * @return list<string>
     */
    private function categoryPath(Asset $asset, $nameById, $parentIdById): array
    {
        $path = [];
        $categoryId = $asset->category_id;

        while ($categoryId !== null && isset($nameById[$categoryId])) {
            array_unshift($path, $nameById[$categoryId]);
            $categoryId = $parentIdById[$categoryId];
        }

        return $path;
    }

    /**
     * Build the nested category tree for the frontend.
     *
     * @return array<int, array<string, mixed>>
     */
    private function categoryTree(): array
    {
        $byParent = Category::query()
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id'])
            ->groupBy(fn (Category $category) => $category->parent_id ?? 0);

        $build = function (int $parentId) use (&$build, $byParent): array {
            return $byParent->get($parentId, collect())
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'children' => $build($category->id),
                ])
                ->all();
        };

        return $build(0);
    }

    /**
     * Resolve the submitted category path names into ids.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function assetAttributes(array $validated): array
    {
        $path = array_values(array_filter(
            array_map('trim', $validated['category_path']),
            fn (string $name) => $name !== '',
        ));

        $node = null;

        foreach ($path as $name) {
            $node = $this->resolveCategoryNode($name, $node);
        }

        $validated['category_id'] = $node->id;

        unset($validated['category_path']);

        return $validated;
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
