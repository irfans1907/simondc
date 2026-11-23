<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Item templates describe the checklist rows for one asset category
     * (for example PAC -> Temperature, Humidity, Indoor, Outdoor blower).
     * Assets flagged in_checklist are materialised into checklist_items
     * from these rows.
     */
    public function up(): void
    {
        Schema::create('checklist_item_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('section')->nullable();
            $table->string('group')->nullable();
            $table->string('label');
            $table->string('unit')->nullable();
            $table->string('input_type')->default('number');
            $table->decimal('min_value', 12, 2)->nullable();
            $table->decimal('max_value', 12, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_item_templates');
    }
};
