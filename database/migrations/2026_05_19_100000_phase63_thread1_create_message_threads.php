<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-63 INBOX-THREAD-1: bi-directional landlord<->tenant message
 * threads on top of Phase 28 [TENANT-PORTAL] notifications.
 *
 * Polymorphic `subject` attaches a thread to a Lease (rent dispute),
 * Ticket (maintenance follow-up), or NULL (standalone inquiry).
 *
 * `version` column reuses the Phase-62 RowVersion conflict-detection
 * pattern so offline replay of a queued thread mutation (e.g. archive
 * while the thread was locked server-side) surfaces a 409 instead of
 * a silent overwrite.
 *
 * The composite `mt_landlord_status_recent` index serves the inbox
 * list query — landlord_id (TenantScope) -> status (open filter) ->
 * last_message_at DESC (sort by recency).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->nullableMorphs('subject');
            $table->string('title', 200)->nullable();
            $table->enum('status', ['open', 'archived', 'locked'])->default('open');
            $table->timestamp('last_message_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['landlord_id', 'status', 'last_message_at'], 'mt_landlord_status_recent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_threads');
    }
};
