<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Replaces the free-text customer/rack/mcb columns with nullable
     * relations. The text columns stay in place as a fallback until the
     * matching command has linked every existing row.
     */
    public function up(): void
    {
        Schema::table('power_load_rows', function (Blueprint $table) {
            $table->foreignId('rack_id')->nullable()->after('power_pdu_id')->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->after('rack_id')->constrained()->nullOnDelete();
            $table->foreignId('panel_mcb_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('power_load_rows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('panel_mcb_id');
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('rack_id');
        });
    }
};
