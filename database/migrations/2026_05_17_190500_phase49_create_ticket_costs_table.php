<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-49 MAINTENANCE-COSTS-1: per-ticket maintenance cost attribution.
 *
 * Category enum captures the source of the cost — parts (auto-seeded
 * by TicketResolutionService::recordParts), vendor (manual entry from
 * a contractor invoice), labor (in-house caretaker time), other.
 *
 * Soft-deletes per DPA-3 audit retention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['parts', 'vendor', 'labor', 'other']);
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3)->default('KES');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ticket_id', 'category'], 'ticket_costs_ticket_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_costs');
    }
};
