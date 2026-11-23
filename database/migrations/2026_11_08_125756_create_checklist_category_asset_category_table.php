<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Maps asset categories onto the checklist categories they feed. One
     * checklist category can collect several asset categories (PAC and AC
     * both feed "PAC & AC"), so this is a many-to-many pivot.
     */
    public function up(): void
    {
        Schema::create('checklist_category_asset_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['checklist_category_id', 'category_id'],
                'checklist_category_category_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_category_asset_category');
    }
};
