<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-31 ONB-EMPTY-3: per-landlord dismiss flag for the EmptyState
 * onboarding checklist. Set when the landlord clicks "Dismiss" on the
 * MilestoneChecklist — the component hides until reset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarding_checklist_dismissed_at')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarding_checklist_dismissed_at');
        });
    }
};
