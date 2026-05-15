<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-25 API-WEBHOOK-1: outbound webhook subscriptions.
 *
 * Landlords register their integration endpoints here so they can
 * receive payment.received / invoice.created / lease.signed events
 * push-style — previously the only outbound integration option was
 * pull-style polling against /api/v1/landlord/* endpoints.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('webhook_subscriptions')) {
            return;
        }

        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('secret', 64);
            $table->json('events');
            $table->boolean('active')->default(true);
            $table->timestamp('last_delivery_at')->nullable();
            $table->timestamps();

            // Phase-19 INDEX-4 convention: (landlord_id, created_at)
            // composite is the canonical TenantScope-friendly index.
            $table->index(['landlord_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_subscriptions');
    }
};
