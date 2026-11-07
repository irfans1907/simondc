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
        Schema::create('checklist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_item_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('time_slot', 5);
            $table->decimal('value', 12, 2)->nullable();
            $table->string('status', 10)->nullable();
            $table->text('note')->nullable();
            $table->string('source', 10)->default('manual');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['checklist_item_id', 'entry_date', 'time_slot']);
            $table->index(['entry_date', 'time_slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_entries');
    }
};
