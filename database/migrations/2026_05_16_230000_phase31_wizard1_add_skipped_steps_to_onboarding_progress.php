<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-31 ONB-WIZARD-1: track skipped steps separately from completed.
 * Pre-Phase-31, OnboardingProgress::skipStep just called completeStep
 * so a skipped step was indistinguishable from a completed one in the
 * activation funnel. Adding a dedicated skipped_steps json column lets
 * activation:audit + onboarding-wizard:audit answer "where do users
 * give up?" honestly.
 *
 * last_touched_at is the wizard-stall detector signal — bumped on
 * every step save so onboarding-wizard:audit can bucket stalls by
 * days since last activity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_progress', function (Blueprint $table) {
            $table->json('skipped_steps')->nullable()->after('completed_steps');
            $table->timestamp('last_touched_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_progress', function (Blueprint $table) {
            $table->dropColumn(['skipped_steps', 'last_touched_at']);
        });
    }
};
