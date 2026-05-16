<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-28 TENANT-MAINT-1: SLA tracking columns on tickets.
 *
 *   sla_due_at        — created_at + Ticket::SEVERITY_SECONDS[priority]
 *   first_response_at — first TicketActivity by landlord|caretaker
 *                       (tenant's own creating activity does NOT count)
 *
 * Composite index supports the nightly tickets:audit-sla command
 * (WHERE sla_due_at < now() AND first_response_at IS NULL) without a
 * full scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('sla_due_at')->nullable()->after('priority');
            $table->timestamp('first_response_at')->nullable()->after('sla_due_at');
            $table->index(['sla_due_at', 'first_response_at'], 'tickets_sla_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_sla_idx');
            $table->dropColumn(['sla_due_at', 'first_response_at']);
        });
    }
};
