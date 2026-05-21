<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-75 PARTS-PRICING-1: an append-only history of a part's unit cost so
 * cost trend + audit are possible (Part.cost_per_unit_cents holds only the
 * current value). Written by PartObserver on create + on cost change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('cost_per_unit_cents');
            $table->string('source', 32)->default('manual'); // manual|purchase_order|import
            $table->timestamp('effective_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['part_id', 'effective_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_price_history');
    }
};
