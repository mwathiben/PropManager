<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-80 ESCALATION: a caretaker who is stuck escalates a ticket to the
 * landlord. An OPEN escalation = escalated_at set AND escalation_acknowledged_at
 * NULL. The landlord acknowledges (or reassigns) to close it. Mirrors the
 * vendor decline → notify-landlord flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('escalated_at')->nullable()->after('resolution_due_at');
            $table->foreignId('escalated_by')->nullable()->after('escalated_at')
                ->constrained('users')->nullOnDelete();
            $table->text('escalation_reason')->nullable()->after('escalated_by');
            $table->timestamp('escalation_acknowledged_at')->nullable()->after('escalation_reason');
            $table->foreignId('escalation_acknowledged_by')->nullable()->after('escalation_acknowledged_at')
                ->constrained('users')->nullOnDelete();
            // Open-escalation queue lookups filter on these two.
            $table->index(['landlord_id', 'escalated_at', 'escalation_acknowledged_at'], 'tickets_open_escalation_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_open_escalation_idx');
            $table->dropConstrainedForeignId('escalated_by');
            $table->dropConstrainedForeignId('escalation_acknowledged_by');
            $table->dropColumn(['escalated_at', 'escalation_reason', 'escalation_acknowledged_at']);
        });
    }
};
