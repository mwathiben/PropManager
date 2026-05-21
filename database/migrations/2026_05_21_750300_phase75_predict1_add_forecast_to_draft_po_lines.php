<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-75 PARTS-PREDICT: record WHY a part was suggested (static threshold vs
 * lead-time buffer) + the projected stockout date so the purchase-orders UI can
 * explain the forecast.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draft_purchase_order_lines', function (Blueprint $table) {
            $table->string('trigger_reason', 32)->default('static')->after('cost_per_unit_cents_snapshot');
            $table->date('projected_stockout_at')->nullable()->after('trigger_reason');
        });
    }

    public function down(): void
    {
        Schema::table('draft_purchase_order_lines', function (Blueprint $table) {
            $table->dropColumn(['trigger_reason', 'projected_stockout_at']);
        });
    }
};
