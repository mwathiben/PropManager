<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-29 WF-LEASE-RENEW-2: lease renewals as discrete rows
 * referencing the original lease — the audit trail of proposed →
 * accepted/rejected → confirmed survives, instead of being lost when
 * the landlord overwrites Lease.end_date in place.
 *
 * proposed_end_date + proposed_rent_amount_cents are LANDLORD inputs;
 * tenant accepts/rejects; on confirm the controller writes the new
 * Lease.end_date + rent_amount atomically in a DB transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->date('proposed_end_date');
            $table->unsignedBigInteger('proposed_rent_amount_cents');
            $table->enum('status', ['proposed', 'accepted', 'rejected', 'confirmed', 'expired'])
                ->default('proposed');
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('proposed_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['lease_id', 'status'], 'lr_lease_status_idx');
            $table->index(['landlord_id', 'status'], 'lr_landlord_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_renewals');
    }
};
