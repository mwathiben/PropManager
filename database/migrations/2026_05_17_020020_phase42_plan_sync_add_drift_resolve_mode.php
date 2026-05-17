<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->enum('drift_resolve_mode', ['manual_review', 'always_app_wins', 'always_stripe_wins'])
                ->default('manual_review')
                ->after('stripe_plan_code');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropColumn('drift_resolve_mode');
        });
    }
};
