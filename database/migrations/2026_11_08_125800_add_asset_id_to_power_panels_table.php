<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Links a PUE panel to its Panel Power asset. Like power_pdus, this is
     * an identity link and does not gate which panels are reported.
     */
    public function up(): void
    {
        Schema::table('power_panels', function (Blueprint $table) {
            $table->foreignId('asset_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('power_panels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_id');
        });
    }
};
