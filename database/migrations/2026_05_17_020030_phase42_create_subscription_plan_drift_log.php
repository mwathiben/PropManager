<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_drift_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('stripe_price_id', 64);
            $table->unsignedBigInteger('app_price_cents');
            $table->unsignedBigInteger('stripe_price_cents');
            $table->enum('drift_resolve_mode_at_time', ['manual_review', 'always_app_wins', 'always_stripe_wins']);
            $table->enum('resolution', ['pending', 'resolved_app_wins', 'resolved_stripe_wins', 'manual_pending']);
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();

            $table->index(['subscription_plan_id', 'detected_at'], 'spdl_plan_id_detected_at');
            $table->index(['resolution', 'detected_at'], 'spdl_resolution_detected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_drift_log');
    }
};
