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
        Schema::create('troubleshoots', function (Blueprint $table) {
            $table->id();
            // A ticket born from a maintenance keeps the link until that
            // maintenance is deleted; manual tickets keep this NULL.
            $table->foreignId('maintenance_id')->nullable()->unique()->constrained('maintenances')->nullOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->date('troubleshoot_date');
            $table->string('result');
            $table->string('evidence_photo')->nullable();
            $table->string('document')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('troubleshoots');
    }
};
