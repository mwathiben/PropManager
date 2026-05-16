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
            $table->boolean('stripe_enabled')->default(false)->after('intasend_environment');
            $table->string('stripe_public_key')->nullable()->after('stripe_enabled');
            $table->text('stripe_secret_key')->nullable()->after('stripe_public_key');
            $table->text('stripe_webhook_secret')->nullable()->after('stripe_secret_key');
        });
    }

    public function down(): void
    {
        Schema::table('payment_configurations', function (Blueprint $table): void {
            $table->dropColumn(['stripe_enabled', 'stripe_public_key', 'stripe_secret_key', 'stripe_webhook_secret']);
        });
    }
};
