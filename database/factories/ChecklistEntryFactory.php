<?php

namespace Database\Factories;

use App\Models\ChecklistEntry;
use App\Models\ChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistEntry>
 */
class ChecklistEntryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ChecklistEntry>
     */
    protected $model = ChecklistEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_item_id' => ChecklistItem::factory(),
            'entry_date' => now()->toDateString(),
            'time_slot' => '09:00',
            'value' => fake()->randomFloat(2, 1, 100),
            'status' => null,
            'note' => null,
            'source' => 'manual',
            'user_id' => null,
        ];
    }
}
