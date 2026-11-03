<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Data-only migration: turns every subcategory row into a categories row
     * parented by its former category, then points assets at the deepest node.
     * The categories table is intentionally left populated on rollback.
     */
    public function up(): void
    {
        $now = now();

        $subcategories = DB::table('subcategories')
            ->select('id', 'category_id', 'name')
            ->get();

        foreach ($subcategories as $subcategory) {
            $exists = DB::table('categories')
                ->where('parent_id', $subcategory->category_id)
                ->where('name', $subcategory->name)
                ->exists();

            if (! $exists) {
                DB::table('categories')->insert([
                    'parent_id' => $subcategory->category_id,
                    'name' => $subcategory->name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $nodeId = DB::table('categories')
                ->where('parent_id', $subcategory->category_id)
                ->where('name', $subcategory->name)
                ->value('id');

            DB::table('assets')
                ->where('subcategory_id', $subcategory->id)
                ->update(['category_id' => $nodeId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
