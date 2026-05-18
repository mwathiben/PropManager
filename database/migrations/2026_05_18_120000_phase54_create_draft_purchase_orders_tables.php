<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-54 PARTS-REORDER-1: draft purchase orders + lines.
 *
 * Sibling to Phase-49 parts / ticket_parts schema. The parts:reorder-
 * suggest cron upserts one DraftPurchaseOrder per (landlord_id,
 * suggested_vendor_id) and writes the below-threshold parts into
 * draft_purchase_order_lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draft_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('suggested_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->enum('status', ['draft', 'sent', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // One open draft per (landlord, vendor) at a time so the cron
            // idempotently upserts instead of creating duplicates on each run.
            $table->unique(['landlord_id', 'status', 'suggested_vendor_id'], 'dpo_unique_open_per_vendor');
            $table->index(['landlord_id', 'status'], 'dpo_landlord_status_idx');
        });

        Schema::create('draft_purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draft_purchase_order_id')->constrained('draft_purchase_orders')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->unsignedInteger('qty_suggested');
            $table->unsignedInteger('cost_per_unit_cents_snapshot');
            $table->timestamps();

            $table->unique(['draft_purchase_order_id', 'part_id'], 'dpo_line_unique_part');
            $table->index('part_id', 'dpo_line_part_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_purchase_order_lines');
        Schema::dropIfExists('draft_purchase_orders');
    }
};
