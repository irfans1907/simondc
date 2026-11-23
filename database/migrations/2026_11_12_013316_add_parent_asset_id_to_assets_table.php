<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('parent_asset_id')
                ->nullable()
                ->after('category_id')
                ->constrained('assets')
                ->nullOnDelete();
        });

        $this->linkBatteryAssetsToUps();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_asset_id');
        });
    }

    /**
     * Existing battery rows were stored as standalone assets that shared the
     * UPS name. Link each of them to the matching UPS asset so the split
     * UPS/Battery tables can group them.
     */
    private function linkBatteryAssetsToUps(): void
    {
        $categories = DB::table('categories')->get(['id', 'name', 'parent_id']);

        if ($categories->isEmpty()) {
            return;
        }

        $byId = $categories->keyBy('id');
        $keywords = ['battery', 'baterai', 'batterai'];

        $isBatteryName = function (string $name) use ($keywords): bool {
            $name = mb_strtolower($name);

            foreach ($keywords as $keyword) {
                if (str_contains($name, $keyword)) {
                    return true;
                }
            }

            return false;
        };

        $isBatteryCategory = function (int $id) use ($byId, $isBatteryName): bool {
            $current = $byId->get($id);

            while ($current !== null) {
                if ($isBatteryName($current->name)) {
                    return true;
                }

                $current = $current->parent_id === null
                    ? null
                    : $byId->get($current->parent_id);
            }

            return false;
        };

        $upsRoot = $categories
            ->first(fn (object $category): bool => $category->parent_id === null
                && mb_strtolower($category->name) === 'ups');

        if ($upsRoot === null) {
            return;
        }

        $childrenByParent = $categories->groupBy(fn (object $category) => $category->parent_id ?? 0);
        $upsCategoryIds = [];

        $collect = function (int $parentId) use (&$collect, $childrenByParent, &$upsCategoryIds): void {
            foreach ($childrenByParent->get($parentId, collect()) as $child) {
                $upsCategoryIds[] = $child->id;
                $collect($child->id);
            }
        };

        $upsCategoryIds[] = $upsRoot->id;
        $collect($upsRoot->id);

        $batteryCategoryIds = array_values(array_filter(
            $upsCategoryIds,
            fn (int $id): bool => $isBatteryCategory($id),
        ));

        $upsCategoryIds = array_values(array_diff($upsCategoryIds, $batteryCategoryIds));

        $upsByName = DB::table('assets')
            ->whereIn('category_id', $upsCategoryIds)
            ->orderBy('id')
            ->get(['id', 'name'])
            ->keyBy(fn (object $asset): string => mb_strtolower(trim($asset->name)));

        DB::table('assets')
            ->whereIn('category_id', $batteryCategoryIds)
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(function (object $asset) use ($upsByName): void {
                $parent = $upsByName->get(mb_strtolower(trim($asset->name)));

                if ($parent === null) {
                    return;
                }

                DB::table('assets')
                    ->where('id', $asset->id)
                    ->update(['parent_asset_id' => $parent->id]);
            });
    }
};
