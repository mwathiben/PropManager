<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-29 WF-LATE-FEE-2: generic landlord task list.
 *
 * Polymorphic `related_to_*` so tasks created by different workflows
 * (late-fee escalation, vacancy detection, future Phase 30
 * integrations) all surface on the same Tasks/Index.vue board.
 * source_workflow names the originating finding for traceability.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landlord_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('task_type', 64);
            $table->morphs('related_to');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'dismissed', 'snoozed'])
                ->default('pending');
            $table->date('due_date')->nullable();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('dismissed_reason')->nullable();
            $table->string('source_workflow', 64);
            $table->timestamps();
            $table->index(['landlord_id', 'status', 'due_date'], 'lt_landlord_status_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landlord_tasks');
    }
};
