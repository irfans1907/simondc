<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('checklist_day_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_category_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('risk_level', 10)->default('LOW');
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['checklist_category_id', 'entry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_day_notes');
    }
};
