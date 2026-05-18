<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-56 AB-AUTO-PROMOTE-2: per-experiment success event name.
 *
 * NULL keeps the Phase-39 default behaviour ("any product_event after
 * the exposure's fired_at counts as conversion"). When the cron sees a
 * non-NULL value it counts only that specific event_name — useful for
 * a payment-flow experiment that doesn't want browse-events polluting
 * the conversion rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiments', function (Blueprint $table): void {
            $table->string('success_event_name', 120)->nullable()->after('winning_variant_key');
        });
    }

    public function down(): void
    {
        Schema::table('experiments', function (Blueprint $table): void {
            $table->dropColumn('success_event_name');
        });
    }
};
