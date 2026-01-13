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
        Schema::table('buildings', function (Blueprint $table) {
            // Water billing configuration per building
            // null = water disabled, 'consumption' = meter-based, 'flat_rate' = fixed monthly charge
            $table->string('water_billing_type', 20)->nullable()->after('caretaker_id');
            $table->decimal('water_flat_rate', 10, 2)->nullable()->after('water_billing_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropColumn(['water_billing_type', 'water_flat_rate']);
        });
    }
};
