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
        Schema::table('assets', function (Blueprint $table) {
            $table->string('alias')->nullable()->after('name');
            $table->string('ip_address')->nullable()->after('alias');
            $table->string('owner')->nullable()->after('ip_address');
            $table->json('details')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['alias', 'ip_address', 'owner', 'details']);
        });
    }
};
