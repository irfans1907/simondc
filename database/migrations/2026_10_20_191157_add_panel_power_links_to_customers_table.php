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
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['panel_pdu', 'mcb_number']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('panel_asset_id')->nullable()->constrained('assets')->restrictOnDelete();
            $table->foreignId('panel_mcb_id')->nullable()->constrained('panel_mcbs')->restrictOnDelete();
            $table->foreignId('panel_asset_id_2')->nullable()->constrained('assets')->restrictOnDelete();
            $table->foreignId('panel_mcb_id_2')->nullable()->constrained('panel_mcbs')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['panel_asset_id']);
            $table->dropForeign(['panel_mcb_id']);
            $table->dropForeign(['panel_asset_id_2']);
            $table->dropForeign(['panel_mcb_id_2']);
            $table->dropColumn([
                'panel_asset_id',
                'panel_mcb_id',
                'panel_asset_id_2',
                'panel_mcb_id_2',
            ]);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('panel_pdu')->nullable();
            $table->string('mcb_number')->nullable();
        });
    }
};
