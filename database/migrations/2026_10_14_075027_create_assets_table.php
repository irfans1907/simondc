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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->string('brand');
            $table->string('type');
            $table->string('model');
            $table->string('serial_number');
            $table->string('capacity');
            $table->string('manufacture_year');
            $table->string('operation_year');
            $table->string('quantity');
            $table->string('floor');
            $table->string('room');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
