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
        Schema::table('troubleshoots', function (Blueprint $table) {
            $table->string('ticket_number')->nullable()->after('id')->unique();
        });

        // Backfill every existing ticket from its primary key so the
        // numbering (TKT-00001) stays consistent with new inserts.
        DB::table('troubleshoots')
            ->whereNull('ticket_number')
            ->orderBy('id')
            ->chunkById(500, function ($tickets) {
                foreach ($tickets as $ticket) {
                    DB::table('troubleshoots')
                        ->where('id', $ticket->id)
                        ->update([
                            'ticket_number' => 'TKT-'.str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('troubleshoots', function (Blueprint $table) {
            $table->dropUnique(['ticket_number']);
            $table->dropColumn('ticket_number');
        });
    }
};
