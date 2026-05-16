<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-35 PLATFORM-ANALYTICS-1: append-only product event stream.
 *
 * Foundation for any later Amplitude/Mixpanel/Heap SDK integration
 * (batch-export rows). Multi-tenant by design — TenantScope on
 * landlord_id ensures cross-tenant funnels never leak.
 *
 * Indexes target query patterns we know about:
 *   - (event_name, created_at) for "how many sign-ups today"
 *   - (landlord_id, created_at) for funnel slices per tenant
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('landlord_id')->nullable();
            $table->string('event_name', 64);
            $table->json('properties')->nullable();
            $table->timestamp('created_at');
            $table->index(['event_name', 'created_at'], 'pe_event_created_idx');
            $table->index(['landlord_id', 'created_at'], 'pe_landlord_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_events');
    }
};
