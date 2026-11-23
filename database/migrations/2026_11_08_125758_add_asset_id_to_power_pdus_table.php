<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Links a PDU to its Panel Power asset. The relation is informational
     * for now; Monthly Check still reads every rack regardless of flags.
     */
    public function up(): void
    {
        Schema::table('power_pdus', function (Blueprint $table) {
            $table->foreignId('asset_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('power_pdus', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_id');
        });
    }
};
