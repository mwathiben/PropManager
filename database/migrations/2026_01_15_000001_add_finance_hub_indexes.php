<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasIndex('payments', 'payments_landlord_method_idx')) {
                $table->index(['landlord_id', 'payment_method'], 'payments_landlord_method_idx');
            }
            if (! Schema::hasIndex('payments', 'payments_landlord_invoice_idx')) {
                $table->index(['landlord_id', 'invoice_id'], 'payments_landlord_invoice_idx');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasIndex('invoices', 'invoices_landlord_status_due_idx')) {
                $table->index(['landlord_id', 'status', 'due_date'], 'invoices_landlord_status_due_idx');
            }
            if (! Schema::hasIndex('invoices', 'invoices_landlord_created_idx')) {
                $table->index(['landlord_id', 'created_at'], 'invoices_landlord_created_idx');
            }
        });

        Schema::table('leases', function (Blueprint $table) {
            if (! Schema::hasIndex('leases', 'leases_landlord_active_idx')) {
                $table->index(['landlord_id', 'is_active'], 'leases_landlord_active_idx');
            }
            if (! Schema::hasIndex('leases', 'leases_unit_idx')) {
                $table->index(['unit_id'], 'leases_unit_idx');
            }
        });

        Schema::table('units', function (Blueprint $table) {
            if (! Schema::hasIndex('units', 'units_building_landlord_idx')) {
                $table->index(['building_id', 'landlord_id'], 'units_building_landlord_idx');
            }
        });
    }

    public function down(): void
    {
        $isMySQL = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb']);

        if ($isMySQL) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            Schema::table('units', function (Blueprint $table) {
                if (Schema::hasIndex('units', 'units_building_landlord_idx')) {
                    $table->dropIndex('units_building_landlord_idx');
                }
            });

            Schema::table('leases', function (Blueprint $table) {
                if (Schema::hasIndex('leases', 'leases_unit_idx')) {
                    $table->dropIndex('leases_unit_idx');
                }
                if (Schema::hasIndex('leases', 'leases_landlord_active_idx')) {
                    $table->dropIndex('leases_landlord_active_idx');
                }
            });

            Schema::table('invoices', function (Blueprint $table) {
                if (Schema::hasIndex('invoices', 'invoices_landlord_created_idx')) {
                    $table->dropIndex('invoices_landlord_created_idx');
                }
                if (Schema::hasIndex('invoices', 'invoices_landlord_status_due_idx')) {
                    $table->dropIndex('invoices_landlord_status_due_idx');
                }
            });

            Schema::table('payments', function (Blueprint $table) {
                if (Schema::hasIndex('payments', 'payments_landlord_invoice_idx')) {
                    $table->dropIndex('payments_landlord_invoice_idx');
                }
                if (Schema::hasIndex('payments', 'payments_landlord_method_idx')) {
                    $table->dropIndex('payments_landlord_method_idx');
                }
            });
        } finally {
            if ($isMySQL) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }
};
