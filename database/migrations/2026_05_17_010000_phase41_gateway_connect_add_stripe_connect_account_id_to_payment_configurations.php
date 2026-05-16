<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_configurations', function (Blueprint $table): void {
            $table->text('stripe_connect_account_id')->nullable()->after('stripe_webhook_secret');
            $table->string('stripe_connect_status', 32)->nullable()->after('stripe_connect_account_id');
            $table->boolean('stripe_connect_charges_enabled')->default(false)->after('stripe_connect_status');
            $table->boolean('stripe_connect_payouts_enabled')->default(false)->after('stripe_connect_charges_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('payment_configurations', function (Blueprint $table): void {
            $table->dropColumn([
                'stripe_connect_account_id',
                'stripe_connect_status',
                'stripe_connect_charges_enabled',
                'stripe_connect_payouts_enabled',
            ]);
        });
    }
};
