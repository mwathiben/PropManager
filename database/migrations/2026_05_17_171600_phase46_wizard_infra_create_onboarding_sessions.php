<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-46 WIZARD-INFRA-1: replace OnboardingProgress.step_data JSON
 * blob with a narrow per-user wizard-state row. Real data lives in
 * canonical models (LandlordProfile, Property, PaymentConfiguration,
 * Lease, Invitation); the session row only tracks wizard navigation +
 * lifecycle timestamps.
 *
 * OnboardingProgress stays around in deprecation but is no longer the
 * source of truth. Phase 47 [WIZARD-MIGRATE] will move
 * OnboardingController step writes from OnboardingProgress.step_data
 * to canonical-model service calls.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_sessions', function (Blueprint $table): void {
            $table->id();
            // NOT UNIQUE — a user can accumulate multiple historical
            // sessions (abandoned + completed); firstFor() returns
            // the live (non-completed AND non-abandoned) row by
            // application-layer query.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['landlord', 'caretaker', 'tenant'])->index();
            $table->unsignedSmallInteger('current_step')->default(1);
            $table->json('step_history')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_touched_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->timestamp('last_nudge_sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'completed_at', 'abandoned_at'], 'onboarding_sessions_live_lookup_idx');
            $table->index(['completed_at', 'abandoned_at', 'last_touched_at'], 'onboarding_sessions_stall_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_sessions');
    }
};
