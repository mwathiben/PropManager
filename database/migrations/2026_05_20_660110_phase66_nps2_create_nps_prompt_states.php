<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-66 NPS-SURVEY-2: server-authoritative prompt cadence state.
 *
 * One row per user (unique user_id). The server — not the client — is
 * the single source of truth for whether to prompt: a tampered or
 * buggy client can neither spam the survey (cadence + reprompt cooldown
 * are enforced here) nor suppress it permanently (only opt_out does
 * that). NpsEligibilityService reads/writes this table exclusively.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nps_prompt_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_prompted_at')->nullable();
            $table->timestamp('last_responded_at')->nullable();
            $table->unsignedSmallInteger('dismiss_count')->default(0);
            $table->timestamp('opted_out_at')->nullable();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nps_prompt_states');
    }
};
