<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-37 PWA-GATEWAY-1: subscription_changes.gateway_response stores
 * the Paystack PUT /subscription/:code/plan response payload so the
 * gateway:proration-audit cron can detect rows where the local DB
 * write succeeded but the gateway call failed (NULL or success=false).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_changes', function (Blueprint $table): void {
            $table->json('gateway_response')->nullable()->after('effective_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_changes', function (Blueprint $table): void {
            $table->dropColumn('gateway_response');
        });
    }
};
