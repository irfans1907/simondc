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
        Schema::table('maintenances', function (Blueprint $table) {
            // A maintenance still Scheduled or On Progress has no outcome
            // yet; the result becomes mandatory only once it is Done
            // (enforced in MaintenanceRequest, not the schema).
            $table->string('result')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->string('result')->nullable(false)->change();
        });
    }
};
