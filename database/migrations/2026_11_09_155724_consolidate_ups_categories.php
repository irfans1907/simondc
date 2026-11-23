<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The old per-part UPS categories. They stay available as sub-categories
     * of the single "UPS" category, but no longer own checklist templates.
     *
     * @var list<string>
     */
    private const LEGACY_NAMES = ['UPS Frame', 'UPS Modul', 'UPS Electronic', 'Battery'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $legacyIds = Category::query()
            ->whereIn('name', self::LEGACY_NAMES)
            ->pluck('id')
            ->all();

        // Fresh install: the category seeder builds the hierarchy instead.
        if ($legacyIds === []) {
            return;
        }

        $ups = Category::query()->firstOrCreate(
            ['name' => 'UPS'],
            ['parent_id' => null],
        );

        if ($ups->parent_id !== null) {
            $ups->update(['parent_id' => null]);
        }

        Category::query()
            ->whereKey($legacyIds)
            ->update(['parent_id' => $ups->id]);

        // Move every asset inside the UPS subtree to the single UPS category.
        DB::table('assets')
            ->whereIn('category_id', $this->descendantIds($ups->id))
            ->update(['category_id' => $ups->id]);

        // The per-part UPS templates are replaced by the single UPS template.
        DB::table('checklist_item_templates')
            ->whereIn('category_id', $legacyIds)
            ->delete();

        // Detach the legacy categories from the checklist mapping. The
        // template seeder reattaches UPS to "UPS 2" as the default.
        DB::table('checklist_category_asset_category')
            ->whereIn('category_id', $legacyIds)
            ->delete();

        $this->backfillChecklistCategories($ups->id);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // One-way data migration: the legacy categories keep their new parent.
    }

    /**
     * Every category id below the given root, including the root.
     *
     * @return list<int>
     */
    private function descendantIds(int $rootId): array
    {
        $ids = [$rootId];
        $pending = [$rootId];

        while ($pending !== []) {
            $children = Category::query()
                ->whereIn('parent_id', $pending)
                ->pluck('id')
                ->all();

            $pending = $children;
            $ids = array_merge($ids, $children);
        }

        return array_values(array_unique($ids));
    }

    /**
     * Give existing checklist-enabled assets an explicit checklist category
     * from the old category mapping. Assets under UPS are skipped: the admin
     * picks their tab manually.
     */
    private function backfillChecklistCategories(int $upsId): void
    {
        $mapping = DB::table('checklist_category_asset_category')
            ->get(['category_id', 'checklist_category_id'])
            ->mapWithKeys(fn (object $row): array => [$row->category_id => $row->checklist_category_id])
            ->all();

        $assets = DB::table('assets')
            ->where('in_checklist', true)
            ->whereNull('checklist_category_id')
            ->get(['id', 'category_id']);

        foreach ($assets as $asset) {
            $rootId = $this->rootId($asset->category_id);

            if ($rootId === null || $rootId === $upsId) {
                continue;
            }

            $checklistCategoryId = $mapping[$rootId] ?? null;

            if ($checklistCategoryId === null) {
                continue;
            }

            DB::table('assets')
                ->where('id', $asset->id)
                ->update(['checklist_category_id' => $checklistCategoryId]);
        }
    }

    /**
     * Resolve the top-level category id for the given category.
     */
    private function rootId(?int $categoryId): ?int
    {
        if ($categoryId === null) {
            return null;
        }

        return Category::query()->find($categoryId)?->root()->id;
    }
};
