<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-103 OWNER-PAYOUTS: a property manager's management fee per owner — the PM's cut,
 * deducted from the owner's statement net (collected - expenses - fee). Default 'none' so
 * existing owners keep their pre-fee net (non-regressive). A single value column
 * interpreted by the type (percentage of collected, or a flat amount per statement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_owners', function (Blueprint $table): void {
            $table->enum('management_fee_type', ['none', 'percentage', 'flat'])->default('none')->after('is_active');
            $table->decimal('management_fee_value', 12, 2)->default(0)->after('management_fee_type');
        });
    }

    public function down(): void
    {
        Schema::table('property_owners', function (Blueprint $table): void {
            $table->dropColumn(['management_fee_type', 'management_fee_value']);
        });
    }
};
