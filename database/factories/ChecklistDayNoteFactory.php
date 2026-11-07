<?php

namespace Database\Factories;

use App\Models\ChecklistCategory;
use App\Models\ChecklistDayNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistDayNote>
 */
class ChecklistDayNoteFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ChecklistDayNote>
     */
    protected $model = ChecklistDayNote::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_category_id' => ChecklistCategory::factory(),
            'entry_date' => now()->toDateString(),
            'risk_level' => 'LOW',
            'note' => null,
            'user_id' => null,
        ];
    }
}
