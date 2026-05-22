<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-86 WATER-METER-FOUNDATION (METER-MODEL-3): re-key water readings to a
 * physical meter. Non-destructive backfill — for every unit that has readings or
 * a recorded meter_number, create one active Meter and point its readings at it.
 * One-meter-per-unit means consumption + billing are byte-for-byte unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('water_readings', function (Blueprint $table) {
            $table->foreignId('meter_id')->nullable()->after('unit_id')
                ->constrained('water_meters')->nullOnDelete();
            $table->index('meter_id');
        });

        $now = now();

        // Units that already have water history or a meter number get a meter.
        $unitIds = DB::table('water_readings')->distinct()->pluck('unit_id')
            ->merge(DB::table('units')->whereNotNull('meter_number')->pluck('id'))
            ->unique()
            ->filter();

        foreach ($unitIds as $unitId) {
            $unit = DB::table('units')->where('id', $unitId)->first();
            if (! $unit) {
                continue;
            }

            $earliest = DB::table('water_readings')
                ->where('unit_id', $unitId)
                ->orderBy('reading_date')
                ->orderBy('id')
                ->first();

            $meterId = DB::table('water_meters')->insertGetId([
                'landlord_id' => $unit->landlord_id,
                'building_id' => $unit->building_id,
                'unit_id' => $unitId,
                'serial_number' => $unit->meter_number,
                'utility_type' => 'water',
                'status' => 'active',
                'initial_reading' => $earliest?->previous_reading ?? 0,
                'installed_at' => $earliest?->reading_date ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('water_readings')
                ->where('unit_id', $unitId)
                ->whereNull('meter_id')
                ->update(['meter_id' => $meterId]);
        }
    }

    public function down(): void
    {
        Schema::table('water_readings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meter_id');
        });
    }
};
