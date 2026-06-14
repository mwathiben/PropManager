<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('property_owners', function (Blueprint $table) {
            // Which figure a percentage fee is charged on. Defaults to 'collected'
            // (the existing managementFeeOn behaviour) so no relationship changes
            // its fee until a manager deliberately picks a different base.
            $table->string('management_fee_base')->default('collected')->after('management_fee_value');

            // For a flat fee: a fixed amount for the whole period, or per unit
            // weighted by each unit's occupied share of the period.
            $table->string('management_fee_flat_cadence')->default('per_period')->after('management_fee_base');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_owners', function (Blueprint $table) {
            $table->dropColumn(['management_fee_base', 'management_fee_flat_cadence']);
        });
    }
};
