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
        Schema::create('power_panel_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('power_panel_id')->constrained()->cascadeOnDelete();
            $table->char('period', 7);
            $table->decimal('amp_r', 8, 2)->nullable();
            $table->decimal('amp_s', 8, 2)->nullable();
            $table->decimal('amp_t', 8, 2)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['power_panel_id', 'period']);
            $table->index('period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('power_panel_readings');
    }
};
