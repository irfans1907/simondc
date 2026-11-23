<?php

namespace Database\Factories;

use App\Models\ChecklistCategory;
use App\Models\ChecklistSlotNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistSlotNote>
 */
class ChecklistSlotNoteFactory extends Factory
{
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
            'time_slot' => '09:00',
            'risk_level' => 'LOW',
            'note' => null,
            'user_id' => null,
        ];
    }
}
