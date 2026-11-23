<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\ChecklistItemTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistItemTemplate>
 */
class ChecklistItemTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ChecklistItemTemplate>
     */
    protected $model = ChecklistItemTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
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
