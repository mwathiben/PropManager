<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-61 TERMINATION-1: lease termination workflow with notice
 * period + tenant/landlord acknowledgment + dispute path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_terminations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->enum('termination_reason', [
                'breach_by_tenant', 'breach_by_landlord', 'mutual',
                'hardship', 'sale', 'other',
            ]);
            $table->date('termination_date');
            $table->timestamp('notice_given_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->enum('status', ['pending', 'acknowledged', 'disputed', 'completed', 'withdrawn'])
                ->default('pending');
            $table->text('reason_text')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['lease_id', 'status'], 'lease_terminations_lease_status');
            $table->index(['landlord_id', 'status'], 'lease_terminations_landlord_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_terminations');
    }
};
