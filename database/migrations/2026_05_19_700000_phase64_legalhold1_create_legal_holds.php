<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-64 LEGAL-HOLD-1: polymorphic hold registry that overrides
 * the Phase 63 messages:enforce-retention sweep. Currently scoped to
 * MessageThread holds — but the polymorphic shape leaves room for
 * future tables (Document, Lease, etc.) to plug in without a fresh
 * migration.
 *
 * Unique(holdable_type, holdable_id, released_at) so a subject can
 * be re-held after release (each held-then-released cycle stamps a
 * new released_at; only ONE active row per subject is permitted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_holds', function (Blueprint $table) {
            $table->id();
            $table->string('holdable_type');
            $table->unsignedBigInteger('holdable_id');
            $table->string('reason', 500);
            $table->foreignId('held_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('held_at');
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['holdable_type', 'holdable_id'], 'lh_subject');
            $table->unique(
                ['holdable_type', 'holdable_id', 'released_at'],
                'lh_subject_active_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_holds');
    }
};
