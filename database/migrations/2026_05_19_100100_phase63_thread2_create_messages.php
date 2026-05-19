<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-63 INBOX-THREAD-2: messages live under message_threads with
 * CASCADE delete (thread-removed implies messages-removed).
 *
 * `sender_id` nullable + nullOnDelete because system messages (type
 * 'system') carry sender_id NULL — e.g. "Thread locked by landlord"
 * audit lines. Foreign-key NULL-on-delete also covers the rare case
 * where a user is hard-deleted (GDPR right-to-erasure) and their
 * historical messages must remain for landlord audit continuity.
 *
 * (thread_id, created_at) composite index serves the thread-detail
 * fetch (load messages chronologically within a thread).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->enum('message_type', ['text', 'system', 'attachment'])->default('text');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['thread_id', 'created_at'], 'm_thread_created');
            $table->index('sender_id', 'm_sender');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
