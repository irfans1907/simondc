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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rack_id')->unique()->constrained('racks')->restrictOnDelete();
            $table->string('city');
            $table->string('location');
            $table->string('customer_name');
            $table->string('service_type')->nullable();
            $table->string('sales_channel')->nullable();
            $table->string('business_status')->nullable();
            $table->string('service_status')->nullable();
            $table->string('order_number')->nullable();
            $table->string('sid_rack')->nullable();
            $table->date('activation_date')->nullable();
            $table->string('power_source')->nullable();
            $table->string('panel_pdu')->nullable();
            $table->string('mcb_number')->nullable();
            $table->string('connectivity')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('switch_type')->nullable();
            $table->string('port')->nullable();
            $table->string('vlan')->nullable();
            $table->string('service_vlan')->nullable();
            $table->string('account_manager')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('customer_name');
            $table->index('service_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
