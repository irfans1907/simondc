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
        Schema::table('troubleshoots', function (Blueprint $table) {
            $table->date('started_date')->nullable()->after('troubleshoot_date');
            $table->date('closed_date')->nullable()->after('started_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('troubleshoots', function (Blueprint $table) {
            $table->dropColumn(['started_date', 'closed_date']);
        });
    }
};
