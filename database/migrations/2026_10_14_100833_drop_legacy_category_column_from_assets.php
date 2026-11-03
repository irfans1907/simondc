<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('category')->nullable()->after('subcategory_id');
            $table->unsignedBigInteger('category_id')->nullable()->change();
        });

        DB::table('categories')->pluck('name', 'id')
            ->each(function ($name, $id) {
                DB::table('assets')->where('category_id', $id)->update(['category' => $name]);
            });

        Schema::table('assets', function (Blueprint $table) {
            $table->index('category');
        });
    }
};
