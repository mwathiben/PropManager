<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase-60 TRIAL-DEPTH-3: extend the Phase-34 cancel_reason enum with
 * 'trial_expired' so TrialAutoExpire can record the precise reason
 * rather than collapsing to 'other'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN cancel_reason ENUM(
            'too_expensive',
            'missing_features',
            'switching_competitor',
            'business_closing',
            'technical_issues',
            'trial_expired',
            'other'
        ) NULL");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN cancel_reason ENUM(
            'too_expensive',
            'missing_features',
            'switching_competitor',
            'business_closing',
            'technical_issues',
            'other'
        ) NULL");
    }
};
