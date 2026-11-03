<?php

namespace Database\Seeders;

use App\Models\RackType;
use Illuminate\Database\Seeder;

class RackTypeSeeder extends Seeder
{
    /**
     * Seed the default rack types (idempotent by name so an existing
     * production table keeps its rows).
     */
    public function run(): void
    {
        foreach (RackType::DEFAULTS as $type) {
            RackType::query()->updateOrCreate(
                ['name' => $type['name']],
                ['capacity' => $type['capacity']],
            );
        }
    }
}
