<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-56 MULTI-TOUCH-1: every touchpoint that contributed to a user's
 * conversion. The Phase-34 referrals table records only the final attribution
 * (referrer → referred); attribution_touchpoints records the whole journey
 * so AttributionModelService can compute first-touch / last-touch / linear /
 * u-shape credit allocations.
 *
 * No TenantScope by default — many touchpoints are recorded before the user
 * has a landlord_id (anonymous visitor, signup). The landlord_id column is
 * nullable + filled when known so dashboards can scope after the fact.
 *
 * Idempotency at write time: the listener checks for an existing
 * (user_id, channel, touched_at within 1s) row before insert — duplicate
 * events from listener re-fires never multiply touchpoints.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribution_touchpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('channel', [
                'referral',
                'organic_search',
                'paid_search',
                'social',
                'email',
                'direct',
                'invitation',
            ]);
            $table->string('medium', 120)->nullable();
            $table->string('campaign', 120)->nullable();
            $table->unsignedBigInteger('landlord_id')->nullable();
            $table->timestamp('touched_at');
            $table->timestamps();

            $table->index(['user_id', 'touched_at'], 'attr_touch_user_time_idx');
            $table->index(['channel', 'touched_at'], 'attr_touch_channel_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribution_touchpoints');
    }
};
