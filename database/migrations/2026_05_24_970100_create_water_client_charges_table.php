<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-97 WATER-CLIENT-BILLING: charges for a water client (a WaterConnection,
 * NOT a lease). The invoices table is lease-coupled (invoices.lease_id is NOT NULL),
 * so water-client billing gets its own specialized table — the analogue of an
 * invoice's water_due line, keyed by the connection instead of a lease. One charge
 * per connection per billing period (idempotent re-runs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_client_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('water_connection_id')->constrained('water_connections')->cascadeOnDelete();
            $table->date('billing_period_start');
            // Metered consumption billed this period; null for a flat-rate line.
            $table->decimal('consumption', 10, 2)->nullable();
            $table->decimal('water_due', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            // due | partial | paid | overdue | voided
            $table->string('status')->default('due');
            $table->date('due_date')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // One charge per connection per period — the biller is idempotent.
            // Explicit short name: the auto-generated one exceeds MySQL's 64-char limit.
            $table->unique(['water_connection_id', 'billing_period_start'], 'wcc_connection_period_unique');
            $table->index(['landlord_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_client_charges');
    }
};
