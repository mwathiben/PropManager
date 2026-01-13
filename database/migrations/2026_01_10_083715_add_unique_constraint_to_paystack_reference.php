<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds unique constraint on paystack_reference to prevent duplicate payments
     * and indexes on frequently queried columns for performance.
     */
    public function up(): void
    {
        // Add unique constraint and indexes to payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->unique('paystack_reference', 'payments_paystack_reference_unique');
            $table->index(['landlord_id', 'payment_date'], 'payments_landlord_date_idx');
        });

        // Add indexes to invoices table for common queries
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['landlord_id', 'status'], 'invoices_landlord_status_idx');
            $table->index(['landlord_id', 'due_date'], 'invoices_landlord_due_date_idx');
            $table->index(['status', 'created_at'], 'invoices_status_created_idx');
        });

        // Add compound index to water_readings for invoice generation queries
        Schema::table('water_readings', function (Blueprint $table) {
            $table->index(['unit_id', 'is_invoiced', 'reading_date'], 'water_readings_unit_invoiced_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_paystack_reference_unique');
            $table->dropIndex('payments_landlord_date_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_landlord_status_idx');
            $table->dropIndex('invoices_landlord_due_date_idx');
            $table->dropIndex('invoices_status_created_idx');
        });

        Schema::table('water_readings', function (Blueprint $table) {
            $table->dropIndex('water_readings_unit_invoiced_date_idx');
        });
    }
};
