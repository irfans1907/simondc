<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add "for order number" to existing VLAN tag request activity
     * descriptions. Switch descriptions are untouched because they read
     * "VLAN tag request switch ..." with no quote after "request".
     */
    public function up(): void
    {
        DB::statement(
            'UPDATE activity_log SET description = REPLACE(description, ?, ?) WHERE description LIKE ?',
            ['VLAN tag request "', 'VLAN tag request for order number "', '%VLAN tag request "%'],
        );
    }

    /**
     * Reverse the wording for existing VLAN tag request descriptions.
     */
    public function down(): void
    {
        DB::statement(
            'UPDATE activity_log SET description = REPLACE(description, ?, ?) WHERE description LIKE ?',
            ['VLAN tag request for order number "', 'VLAN tag request "', '%VLAN tag request for order number "%'],
        );
    }
};
