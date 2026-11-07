<?php

namespace Database\Factories;

use App\Models\ChecklistCategory;
use App\Models\ChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ChecklistItem>
     */
    protected $model = ChecklistItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_category_id' => ChecklistCategory::factory(),
            'asset_id' => null,
            'scope' => 'infrastructure',
            'section' => null,
            'group' => null,
            'label' => fake()->words(2, true),
            'unit' => null,
            'input_type' => 'number',
            'min_value' => null,
            'max_value' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
