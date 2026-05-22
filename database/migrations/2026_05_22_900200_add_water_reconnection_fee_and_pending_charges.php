<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-90 RECONNECT-FEE: a configurable water reconnection fee + a small
 * pending-charge table (no ad-hoc charge mechanism existed). On reconnect a
 * pending charge is recorded and the next invoice folds it into water_due.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['payment_configurations', 'buildings'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('water_reconnection_fee', 10, 2)->nullable();
            });
        }

        Schema::create('water_pending_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('type')->default('reconnection_fee');
            $table->string('description')->nullable();
            $table->foreignId('applied_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['lease_id', 'applied_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_pending_charges');

        foreach (['payment_configurations', 'buildings'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('water_reconnection_fee');
            });
        }
    }
};
