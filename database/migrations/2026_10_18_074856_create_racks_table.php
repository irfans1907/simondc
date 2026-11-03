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
        Schema::create('racks', function (Blueprint $table) {
            $table->id();
            $table->string('rack_code')->unique();
            $table->string('floor');
            $table->string('room');
            $table->string('status')->default('Ready For Sale');
            $table->string('brand');
            $table->string('type');
            $table->string('capacity');
            $table->string('dimension');
            $table->string('pdu_outlet_1');
            $table->string('pdu_outlet_2');
            $table->string('manufacture_year');
            $table->string('operation_year');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('rack_code');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('racks');
    }
};
