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
        Schema::create('power_load_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('power_pdu_id')->constrained()->cascadeOnDelete();
            $table->string('customer')->nullable();
            $table->string('rack')->nullable();
            $table->string('rack_type')->nullable();
            $table->string('mcb')->nullable();
            $table->decimal('capacity_amp', 8, 2)->nullable();
            $table->string('phase', 1)->nullable();
            $table->string('service')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['power_pdu_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('power_load_rows');
    }
};
