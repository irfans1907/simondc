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
            $table->dropConstrainedForeignId('subcategory_id');
        });

        Schema::dropIfExists('subcategories');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['category_id', 'name']);
            $table->index('name');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('subcategory_id')->nullable()->constrained('subcategories')->restrictOnDelete()->after('category_id');
        });
    }
};
