<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-49 PARTS-INVENTORY-1: per-landlord parts catalogue with stock
 * levels and reorder thresholds.
 *
 * Soft-deletes per DPA-3 retention pattern — a landlord retiring a SKU
 * keeps history available; the row stays after delete().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('sku', 64)->nullable();
            $table->string('category', 64)->nullable();
            $table->unsignedBigInteger('cost_per_unit_cents')->default(0);
            $table->integer('qty_available')->default(0);
            $table->integer('reorder_threshold')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('sku', 'parts_sku_idx');
            $table->index(['landlord_id', 'is_active'], 'parts_landlord_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
