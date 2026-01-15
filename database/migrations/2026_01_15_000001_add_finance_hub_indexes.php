<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // payments_landlord_date_idx already exists from 2026_01_10_083715 migration
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['landlord_id', 'payment_method'], 'payments_landlord_method_idx');
            $table->index(['landlord_id', 'invoice_id'], 'payments_landlord_invoice_idx');
        });

        // invoices_landlord_status_idx already exists from 2026_01_10_083715 migration
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['landlord_id', 'status', 'due_date'], 'invoices_landlord_status_due_idx');
            $table->index(['landlord_id', 'created_at'], 'invoices_landlord_created_idx');
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->index(['landlord_id', 'is_active'], 'leases_landlord_active_idx');
            $table->index(['unit_id'], 'leases_unit_idx');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->index(['building_id', 'landlord_id'], 'units_building_landlord_idx');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex('units_building_landlord_idx');
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->dropIndex('leases_unit_idx');
            $table->dropIndex('leases_landlord_active_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_landlord_created_idx');
            $table->dropIndex('invoices_landlord_status_due_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_landlord_invoice_idx');
            $table->dropIndex('payments_landlord_method_idx');
        });
    }
};
