<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Data-only migration: converts legacy string categories on assets into
     * categories rows and links them via category_id. The categories table is
     * intentionally left populated on rollback so the seeded defaults survive.
     */
    public function up(): void
    {
        $now = now();

        $names = DB::table('assets')
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        foreach ($names as $name) {
            $exists = DB::table('categories')->where('name', $name)->exists();

            if (! $exists) {
                DB::table('categories')->insert([
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach (DB::table('categories')->pluck('id', 'name') as $name => $id) {
            DB::table('assets')->where('category', $name)->update(['category_id' => $id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('assets')->update(['category_id' => null]);
    }
};
