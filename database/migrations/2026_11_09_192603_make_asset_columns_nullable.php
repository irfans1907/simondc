<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The asset list sheets leave many columns empty (brand, model, years,
     * room...), so those columns must accept null.
     *
     * @var list<string>
     */
    private const COLUMNS = [
        'brand',
        'type',
        'model',
        'serial_number',
        'capacity',
        'manufacture_year',
        'operation_year',
        'quantity',
        'floor',
        'room',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->string($column)->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Existing rows may now contain nulls; keep the columns nullable.
    }
};
