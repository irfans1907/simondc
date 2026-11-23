<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class AssetCategorySeeder extends Seeder
{
    /**
     * Seed the default root asset categories.
     */
    public function run(): void
    {
        $existing = Category::query()
            ->pluck('name')
            ->map(fn (string $name) => mb_strtolower($name))
            ->all();

        foreach (Category::DEFAULTS as $name) {
            if (in_array(mb_strtolower($name), $existing, true)) {
                continue;
            }

            Category::create(['parent_id' => null, 'name' => $name]);
        }

        $this->nestLegacyUpsCategories();
    }

    /**
     * Keep the old per-part UPS categories available, but under the single
     * "UPS" root so every UPS asset resolves one shared template.
     */
    private function nestLegacyUpsCategories(): void
    {
        $ups = Category::query()
            ->whereNull('parent_id')
            ->where('name', 'UPS')
            ->first();

        if ($ups === null) {
            return;
        }

        Category::query()
            ->whereNull('parent_id')
            ->whereIn('name', ['UPS Frame', 'UPS Modul', 'UPS Electronic', 'Battery'])
            ->update(['parent_id' => $ups->id]);
    }
}
