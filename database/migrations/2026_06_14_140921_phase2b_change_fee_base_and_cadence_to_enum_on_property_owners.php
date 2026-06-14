<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Change management_fee_base and management_fee_flat_cadence columns from
     * string to DB enum, matching management_fee_type (already an enum).
     * Columns are dormant and carry only default values, so this is safe.
     */
    public function up(): void
    {
        // Defensive: normalize any out-of-set / null values BEFORE the ENUM ->change(),
        // so the migration can never hard-fail on pre-existing data (these were string
        // columns until now). Dormant columns should already be all-defaults, but we
        // do not rely on that assumption.
        DB::table('property_owners')
            ->where(fn ($q) => $q->whereNotIn('management_fee_base', ['collected', 'billed', 'scheduled'])->orWhereNull('management_fee_base'))
            ->update(['management_fee_base' => 'collected']);

        DB::table('property_owners')
            ->where(fn ($q) => $q->whereNotIn('management_fee_flat_cadence', ['per_period', 'per_unit'])->orWhereNull('management_fee_flat_cadence'))
            ->update(['management_fee_flat_cadence' => 'per_period']);

        Schema::table('property_owners', function (Blueprint $table) {
            $table->enum('management_fee_base', ['collected', 'billed', 'scheduled'])
                ->default('collected')
                ->after('management_fee_value')
                ->change();

            $table->enum('management_fee_flat_cadence', ['per_period', 'per_unit'])
                ->default('per_period')
                ->after('management_fee_base')
                ->change();
        });
    }

    /**
     * Revert to string columns (original state after Phase2B migration).
     */
    public function down(): void
    {
        Schema::table('property_owners', function (Blueprint $table) {
            $table->string('management_fee_base')
                ->default('collected')
                ->after('management_fee_value')
                ->change();

            $table->string('management_fee_flat_cadence')
                ->default('per_period')
                ->after('management_fee_base')
                ->change();
        });
    }
};
