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
            $table->text('kra_pin')->nullable()->after('stripe_connect_payouts_enabled');
            $table->unsignedInteger('vat_rate_bps_override')->nullable()->after('kra_pin');
            $table->boolean('stripe_tax_enabled')->default(false)->after('vat_rate_bps_override');
        });
    }

    public function down(): void
    {
        Schema::table('payment_configurations', function (Blueprint $table): void {
            $table->dropColumn(['kra_pin', 'vat_rate_bps_override', 'stripe_tax_enabled']);
        });
    }
};
