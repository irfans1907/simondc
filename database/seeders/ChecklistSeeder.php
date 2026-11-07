<?php

namespace Database\Seeders;

use App\Models\ChecklistCategory;
use App\Models\ChecklistItem;
use Illuminate\Database\Seeder;

class ChecklistSeeder extends Seeder
{
    /**
     * Seed the infrastructure checklist categories and items from
     * config/checklist.php. Rows are matched on their natural key so
     * re-running the seeder keeps existing entries and only updates the
     * descriptive fields.
     */
    public function run(): void
    {
        foreach (config('checklist.categories', []) as $categoryData) {
            $category = ChecklistCategory::updateOrCreate(
                ['slug' => $categoryData['slug']],
                [
                    'name' => $categoryData['name'],
                    'time_slots' => $categoryData['time_slots'],
                    'sort_order' => $categoryData['sort_order'],
                    'is_active' => true,
                ],
            );

            $keptItemIds = [];
            $sortOrder = 0;

            foreach ($categoryData['items'] as $itemData) {
                $item = ChecklistItem::updateOrCreate(
                    [
                        'checklist_category_id' => $category->id,
                        'section' => $itemData['section'] ?? null,
                        'group' => $itemData['group'] ?? null,
                        'label' => $itemData['label'],
                    ],
                    [
                        'scope' => 'infrastructure',
                        'unit' => $itemData['unit'] ?? null,
                        'input_type' => $itemData['input_type'] ?? 'number',
                        'min_value' => $itemData['min_value'] ?? null,
                        'max_value' => $itemData['max_value'] ?? null,
                        'sort_order' => $sortOrder++,
                        'is_active' => true,
                    ],
                );

                $keptItemIds[] = $item->id;
            }

            // Items removed from the config are deactivated instead of
            // deleted so their historical entries survive.
            $category->items()
                ->whereNotIn('id', $keptItemIds)
                ->update(['is_active' => false]);
        }
    }
}
