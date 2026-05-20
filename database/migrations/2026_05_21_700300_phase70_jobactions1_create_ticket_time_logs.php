<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-70 JOB-ACTIONS-1: vendor labour-time entries against a ticket.
 * Underpins the vendor statement + SLA views.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->unsignedSmallInteger('minutes');
            $table->string('note', 500)->nullable();
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->index(['ticket_id', 'vendor_id'], 'ttl_ticket_vendor');
            $table->index(['vendor_id', 'logged_at'], 'ttl_vendor_logged');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_time_logs');
    }
};
