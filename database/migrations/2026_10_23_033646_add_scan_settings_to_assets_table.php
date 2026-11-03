<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The two flags gate which sections the barcode scan page shows for
     * an asset: its checklist, its maintenance history, or both. Both
     * default to true so existing assets keep every section enabled.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->boolean('in_checklist')->default(true)->after('room');
            $table->boolean('in_maintenance')->default(true)->after('in_checklist');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['in_checklist', 'in_maintenance']);
        });
    }
};
