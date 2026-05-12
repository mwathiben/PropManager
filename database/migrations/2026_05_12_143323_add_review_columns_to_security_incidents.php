<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-13 BREACH-7: 30-day post-incident review tracking. ICO/ODPC
 * expect a follow-up report after a breach (root cause, mitigation,
 * recurrence prevention). Without these columns, no scheduled
 * command can surface overdue reviews.
 *
 * review_due_at  — set automatically by Phase-13 BREACH-7 code path
 *                  to reported_at + 30d on incident creation
 * review_completed_at — operator-stamped when the review report is
 *                  filed
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_incidents', function (Blueprint $table) {
            $table->timestamp('review_due_at')->nullable();
            $table->timestamp('review_completed_at')->nullable();
            $table->index('review_due_at', 'security_incidents_review_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('security_incidents', function (Blueprint $table) {
            $table->dropIndex('security_incidents_review_due_idx');
            $table->dropColumn(['review_due_at', 'review_completed_at']);
        });
    }
};
