<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ChecklistCategory;
use App\Models\ChecklistItemTemplate;
use Illuminate\Database\Seeder;

class ChecklistTemplateSeeder extends Seeder
{
    /**
     * Seed the asset -> checklist category mapping and the per-asset
     * checklist item templates from config/checklist_templates.php.
     */
    public function run(): void
    {
        $definitions = config('checklist_templates', []);

        $this->seedMappings($definitions['mappings'] ?? []);
        $this->seedTemplates($definitions['templates'] ?? []);
    }

    /**
     * Attach top-level asset categories to their checklist category.
     *
     * @param  array<string, list<string>>  $mappings
     */
    private function seedMappings(array $mappings): void
    {
        foreach ($mappings as $slug => $categoryNames) {
            $checklistCategory = ChecklistCategory::query()
                ->where('slug', $slug)
                ->first();

            if ($checklistCategory === null) {
                continue;
            }

            $categoryIds = collect($categoryNames)
                ->map(fn (string $name): ?int => Category::query()
                    ->whereNull('parent_id')
                    ->where('name', $name)
                    ->value('id'))
                ->filter()
                ->values()
                ->all();

            $checklistCategory->assetCategories()->sync($categoryIds);
        }
    }

    /**
     * Upsert one template per item and deactivate templates that were
     * removed from the config.
     *
     * @param  array<string, list<array<string, mixed>>>  $templates
     */
    private function seedTemplates(array $templates): void
    {
        foreach ($templates as $categoryName => $items) {
            $category = Category::query()
                ->whereNull('parent_id')
                ->where('name', $categoryName)
                ->first();

            if ($category === null) {
                continue;
            }

            $keptIds = [];
            $sortOrder = 0;

            foreach ($items as $item) {
                $template = ChecklistItemTemplate::updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'section' => $item['section'] ?? null,
                        'group' => $item['group'] ?? null,
                        'label' => $item['label'],
                    ],
                    [
                        'unit' => $item['unit'] ?? null,
                        'input_type' => $item['input_type'] ?? 'number',
                        'min_value' => $item['min_value'] ?? null,
                        'max_value' => $item['max_value'] ?? null,
                        'sort_order' => $sortOrder++,
                        'is_active' => true,
                    ],
                );

                $keptIds[] = $template->id;
            }

            ChecklistItemTemplate::query()
                ->where('category_id', $category->id)
                ->whereNotIn('id', $keptIds)
                ->update(['is_active' => false]);
        }
    }
}
