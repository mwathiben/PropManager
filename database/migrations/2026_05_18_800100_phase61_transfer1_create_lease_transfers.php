<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-61 TRANSFER-1: lease assignment / sublet workflow with
 * three actors (outgoing tenant, incoming tenant, landlord
 * approval). Status moves requested → landlord_approved → completed
 * (or rejected / withdrawn).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('outgoing_tenant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('incoming_tenant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->date('transfer_date');
            $table->timestamp('landlord_approved_at')->nullable();
            $table->enum('status', ['requested', 'landlord_approved', 'completed', 'rejected', 'withdrawn'])
                ->default('requested');
            $table->decimal('transfer_fee_amount', 10, 2)->nullable();
            $table->text('reason_text')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['lease_id', 'status'], 'lease_transfers_lease_status');
            $table->index(['incoming_tenant_id', 'status'], 'lease_transfers_incoming_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_transfers');
    }
};
