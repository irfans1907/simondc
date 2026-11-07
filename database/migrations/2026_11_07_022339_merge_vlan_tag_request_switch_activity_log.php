<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Merge existing VLAN tag request switch activity into the parent
     * `vlan_tag_request` log name so both share one activity group.
     */
    public function up(): void
    {
        DB::table('activity_log')
            ->where('log_name', 'vlan_tag_request_switch')
            ->update(['log_name' => 'vlan_tag_request']);
    }

    /**
     * Reverse the merge for rows whose description still identifies them
     * as switch activity.
     */
    public function down(): void
    {
        DB::table('activity_log')
            ->where('log_name', 'vlan_tag_request')
            ->where('description', 'like', '%VLAN tag request switch%')
            ->update(['log_name' => 'vlan_tag_request_switch']);
    }
};
