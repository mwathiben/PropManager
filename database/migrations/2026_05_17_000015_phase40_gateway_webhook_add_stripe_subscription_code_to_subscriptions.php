<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('stripe_subscription_code', 64)->nullable()->after('paystack_subscription_code');
            $table->string('stripe_customer_code', 64)->nullable()->after('stripe_subscription_code');
            $table->index('stripe_subscription_code', 'subs_stripe_sub_code_idx');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex('subs_stripe_sub_code_idx');
            $table->dropColumn(['stripe_subscription_code', 'stripe_customer_code']);
        });
    }
};
