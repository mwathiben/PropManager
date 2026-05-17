<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-49 PARTS-INVENTORY-2: pivot capturing which Ticket consumed
 * which Part(s) and the cost allocated at the moment of recording
 * (price snapshot — historical attribution stays correct if cost
 * changes later).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->integer('qty_used');
            $table->unsignedBigInteger('cost_allocated_cents');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['ticket_id', 'part_id'], 'ticket_parts_ticket_part_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_parts');
    }
};
