<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-98 WATER-CLIENT-INVOICING-UNIFY: water-client billing now uses real invoices
 * (invoices.water_connection_id), so the parallel Phase-97 water_client_charges table
 * is retired. The feature never reached production, so there is nothing to migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('water_client_charges');
    }

    public function down(): void
    {
        // Recreate the Phase-97 shape so a rollback restores the table (data is gone).
        Schema::create('water_client_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('water_connection_id')->constrained('water_connections')->cascadeOnDelete();
            $table->date('billing_period_start');
            $table->decimal('consumption', 10, 2)->nullable();
            $table->decimal('water_due', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('status')->default('due');
            $table->date('due_date')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['water_connection_id', 'billing_period_start'], 'wcc_connection_period_unique');
            $table->index(['landlord_id', 'status']);
        });
    }
};
