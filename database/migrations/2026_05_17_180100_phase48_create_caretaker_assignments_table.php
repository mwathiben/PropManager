<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-48 CARETAKER-ASSIGNMENT-UX-1: append-only audit of caretaker
 * building assignments.
 *
 * buildings.caretaker_id stays as the canonical "currently assigned"
 * single-FK link; this table captures the workflow (when assigned,
 * whether accepted/declined, decision reason) so the wizard can
 * surface a real accept/decline action at step 2.
 *
 * Unique (caretaker_id, building_id) — exactly one assignment row per
 * (caretaker, building) pair; status flips on the existing row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caretaker_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caretaker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->timestamp('assigned_at');
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_reason', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['caretaker_id', 'building_id'], 'caretaker_assignments_caretaker_building_unique');
            $table->index('status', 'caretaker_assignments_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caretaker_assignments');
    }
};
