<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-34 GROWTH-CHURN-1: capture WHY a subscription was cancelled,
 * not just when. Distinguishes voluntary (too_expensive, missing_
 * features, switching_competitor, business_closing) from involuntary
 * (technical_issues — usually failed payment auto-cancel) so each
 * gets its own remediation playbook.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->enum('cancel_reason', [
                'too_expensive',
                'missing_features',
                'switching_competitor',
                'business_closing',
                'technical_issues',
                'other',
            ])->nullable()->after('cancelled_at');
            $table->text('cancel_feedback')->nullable()->after('cancel_reason');
            $table->index('cancel_reason', 'subs_cancel_reason_idx');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex('subs_cancel_reason_idx');
            $table->dropColumn(['cancel_reason', 'cancel_feedback']);
        });
    }
};
