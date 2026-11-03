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
            ->whereNull('parent_id')
            ->pluck('name')
            ->map(fn (string $name) => mb_strtolower($name))
            ->all();

        foreach (Category::DEFAULTS as $name) {
            if (in_array(mb_strtolower($name), $existing, true)) {
                continue;
            }

            Category::create(['parent_id' => null, 'name' => $name]);
        }
    }
}
