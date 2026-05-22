<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-86 WATER-METER-FOUNDATION (METER-MODEL-1): a first-class meter entity.
 * Before this, water readings keyed off unit_id and units.meter_number was an
 * unused string — no lifecycle, baseline, sub-meter link or replacement history.
 *
 * utility_type is carried so the model could extend to electricity/gas later
 * (decision: water-only-but-extensible) — other utilities are NOT implemented.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            // Nullable: a free-standing water line (e.g. a borehole feeding a
            // neighbour — Phase 94 water clients) has no unit.
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            // Sub-meter -> main meter (enables main-vs-sub reconciliation in Phase 91).
            $table->foreignId('parent_meter_id')->nullable()->constrained('water_meters')->nullOnDelete();
            $table->string('serial_number')->nullable();
            $table->string('utility_type')->default('water');
            $table->string('meter_type')->nullable();
            $table->string('status')->default('active');
            // The non-zero baseline: a meter's first reading is measured from this,
            // not from 0 (initial meter reads are frequently non-zero).
            $table->decimal('initial_reading', 10, 2)->default(0);
            $table->date('installed_at')->nullable();
            $table->date('decommissioned_at')->nullable();
            $table->foreignId('replaced_by_meter_id')->nullable()->constrained('water_meters')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['landlord_id', 'status']);
            $table->index('unit_id');
            $table->index('parent_meter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_meters');
    }
};
