<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-90 WATER-ARREARS-ENFORCEMENT: a meter's water service can be cut for
 * non-payment. Modelled as separate fields (NOT a MeterStatus value) so the
 * meter stays status=active device-wise — Meter::active()/resolveActiveForUnit
 * keep working — while service is disconnected_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('water_meters', function (Blueprint $table) {
            $table->timestamp('disconnected_at')->nullable()->after('decommissioned_at');
            $table->string('disconnect_reason')->nullable()->after('disconnected_at');
        });
    }

    public function down(): void
    {
        Schema::table('water_meters', function (Blueprint $table) {
            $table->dropColumn(['disconnected_at', 'disconnect_reason']);
        });
    }
};
