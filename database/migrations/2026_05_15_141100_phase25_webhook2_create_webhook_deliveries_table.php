<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-25 API-WEBHOOK-2: per-attempt webhook delivery log.
 *
 * Every outbound dispatch (including retries) writes one row here so
 * landlords can diagnose flaky integrator endpoints. Mirrors the
 * Phase-16 RESIL-8 inbound WebhookDeadLetter pattern for outbound.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('webhook_deliveries')) {
            return;
        }

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_subscription_id')->constrained('webhook_subscriptions')->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->json('payload');
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('dead_lettered')->default(false);
            $table->timestamps();

            $table->index(['webhook_subscription_id', 'created_at']);
            $table->index(['dead_lettered', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
