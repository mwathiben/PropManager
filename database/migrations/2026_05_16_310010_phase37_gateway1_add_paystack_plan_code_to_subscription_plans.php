<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-37 PWA-GATEWAY-1: subscription_plans.paystack_plan_code stores
 * the Paystack-side plan identifier minted by PaystackSubscriptionService
 * ::createPlan so SubscriptionService::changePlan can pass it to
 * PUT /subscription/:code/plan when upgrading.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->string('paystack_plan_code', 64)->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropColumn('paystack_plan_code');
        });
    }
};
