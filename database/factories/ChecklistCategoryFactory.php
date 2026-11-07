<?php

namespace Database\Factories;

use App\Models\ChecklistCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChecklistCategory>
 */
class ChecklistCategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ChecklistCategory>
     */
    protected $model = ChecklistCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name),
            'name' => Str::title($name),
            'time_slots' => ['09:00', '14:00', '17:00', '21:00', '06:00'],
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
