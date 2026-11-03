<?php

namespace App\Http\Controllers;

use App\Events\AssetsChanged;
use App\Models\Category;
use App\Models\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetCategoryController extends Controller
{
    /**
     * Show the asset category management page.
     */
    public function index(Request $request): Response
    {
        $canManage = $request->user()->hasPermissionTo('manage asset management');

        $childMenu = Menu::where('url', '/'.$request->path())->first();

        return Inertia::render('dynamic/assets/categorylist', [
            ...$this->childMenuProps($childMenu?->parent, $childMenu),
            'categories' => $this->flatTree(),
            'canManage' => $canManage,
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCategory($request);

        Category::create([
            'parent_id' => $data['parent_id'],
            'name' => $data['name'],
        ]);

        AssetsChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return to_route('asset-categories.index');
    }

    /**
     * Update the given category.
     */
    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validateCategory($request, $category);

        $category->update(['name' => $data['name']]);

        AssetsChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category updated.')]);

        return to_route('asset-categories.index');
    }

    /**
     * Delete the given category when it is not in use.
     */
    public function destroy(Category $category): RedirectResponse
    {
        if ($category->children()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Category still has sub-categories.'),
            ]);

            return to_route('asset-categories.index');
        }

        if ($category->assets()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Category is still used by assets.'),
            ]);

            return to_route('asset-categories.index');
        }

        $category->delete();

        AssetsChanged::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted.')]);

        return to_route('asset-categories.index');
    }

    /**
     * Delete the given categories that are not in use.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $categories = Category::query()->whereIn('id', $data['ids'])->get();

        $deleted = 0;
        $skipped = 0;

        foreach ($categories as $category) {
            if ($category->children()->exists() || $category->assets()->exists()) {
                $skipped++;

                continue;
            }

            $category->delete();
            $deleted++;
        }

        AssetsChanged::dispatch();

        if ($deleted === 0) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No categories were deleted. Categories with sub-categories or assets cannot be deleted.'),
            ]);

            return to_route('asset-categories.index');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':count categories deleted. :skipped skipped because they still have sub-categories or assets.', [
                'count' => $deleted,
                'skipped' => $skipped,
            ]),
        ]);

        return to_route('asset-categories.index');
    }

    /**
     * Validate an incoming category payload, scoped to its parent.
     *
     * @return array{parent_id: int|null, name: string}
     */
    private function validateCategory(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => [
                'required',
                'string',
                'max:100',
                function (string $attribute, mixed $value, callable $fail) use ($request, $category) {
                    $parentId = $request->integer('parent_id') ?: null;
                    $taken = Category::query()
                        ->when(
                            $parentId === null,
                            fn ($query) => $query->whereNull('parent_id'),
                            fn ($query) => $query->where('parent_id', $parentId),
                        )
                        ->when(
                            $category !== null,
                            fn ($query) => $query->where('id', '!=', $category->id),
                        )
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $value))])
                        ->exists();

                    if ($taken) {
                        $fail(__('The name has already been taken.'));
                    }
                },
            ],
        ]);

        return [
            'parent_id' => $data['parent_id'] ?? null,
            'name' => trim((string) $data['name']),
        ];
    }

    /**
     * Flatten the category tree into depth-annotated rows for the table.
     *
     * @return list<array{id: int, name: string, parent_id: int|null, depth: int, assets_count: int}>
     */
    private function flatTree(): array
    {
        $all = Category::query()
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id'])
            ->keyBy('id');

        $ownAssetsCount = Category::query()
            ->withCount('assets')
            ->get()
            ->pluck('assets_count', 'id');

        $descendantCount = [];

        $countDescendants = function (int $categoryId) use (&$countDescendants, $all, $ownAssetsCount, &$descendantCount): int {
            if (isset($descendantCount[$categoryId])) {
                return $descendantCount[$categoryId];
            }

            $total = $ownAssetsCount[$categoryId] ?? 0;

            foreach ($all->where('parent_id', $categoryId) as $child) {
                $total += $countDescendants($child->id);
            }

            return $descendantCount[$categoryId] = $total;
        };

        $rows = [];

        $build = function (?int $parentId, int $depth) use (&$build, &$rows, $all, $countDescendants) {
            foreach ($all->where('parent_id', $parentId) as $node) {
                $rows[] = [
                    'id' => $node->id,
                    'name' => $node->name,
                    'parent_id' => $node->parent_id,
                    'depth' => $depth,
                    'assets_count' => $countDescendants($node->id),
                ];

                $build($node->id, $depth + 1);
            }
        };

        $build(null, 0);

        return $rows;
    }
}
