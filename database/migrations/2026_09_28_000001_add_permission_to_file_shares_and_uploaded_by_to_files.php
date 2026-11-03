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
        Schema::table('file_shares', function (Blueprint $table) {
            $table->enum('permission', ['read', 'write'])->default('read')->after('shared_with');
        });

        Schema::table('files', function (Blueprint $table) {
            $table->unsignedBigInteger('uploaded_by')->nullable()->after('user_id');
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('file_shares', function (Blueprint $table) {
            $table->dropColumn('permission');
        });

        Schema::table('files', function (Blueprint $table) {
            $table->dropForeign(['uploaded_by']);
            $table->dropColumn('uploaded_by');
        });
    }
};
