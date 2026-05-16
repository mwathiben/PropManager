<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-31 ONB-SAMPLE-1: per-row ledger of sample-data inserts so a
 * prospect can "Reset to clean state" without trashing real rows.
 * Each populate() call writes one parent row per (run, polymorphic
 * source) so reset() can walk the log oldest-first and delete the
 * referenced rows in dependency order.
 *
 * Status machine: populated -> reset_pending -> reset_done. Keeping
 * the row after reset lets us prove (in tests + dashboards) that the
 * landlord has used sample data before — useful for funnel telemetry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_data_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['populated', 'reset_pending', 'reset_done'])->default('populated');
            $table->timestamp('populated_at');
            $table->timestamp('reset_at')->nullable();
            $table->json('row_refs');
            $table->timestamps();

            $table->index(['landlord_id', 'status'], 'sdr_landlord_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_data_runs');
    }
};
