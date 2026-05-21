<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-75 PARTS-PRICING-2: per-supplier (vendor) catalogue entry for a part —
 * unit cost, lead time, and minimum order qty. Enables supplier comparison +
 * the lead-time-aware reorder (PARTS-PREDICT). Both FKs are landlord-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('unit_cost_cents');
            $table->unsignedInteger('lead_time_days')->default(7);
            $table->unsignedInteger('min_order_qty')->default(1);
            $table->timestamps();

            $table->unique(['part_id', 'vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_suppliers');
    }
};
