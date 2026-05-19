<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-63 INBOX-THREAD-3: participant pivot enabling cross-tenant
 * isolation that TenantScope alone cannot enforce.
 *
 * Two tenants under the same landlord_id MUST NOT see each other's
 * threads even though TenantScope would otherwise grant them visibility
 * (the trait filters by landlord_id only). Every inbox query routes
 * through MessageThread::scopeForUser($user) which joins through this
 * pivot — making participant membership the authoritative isolation
 * boundary.
 *
 * unique(thread_id, user_id) prevents double-add. Reverse-order
 * (user_id, thread_id) index serves the scopeForUser join from the
 * user side (more selective for tenants).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_thread_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['landlord', 'caretaker', 'tenant']);
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['thread_id', 'user_id'], 'mtp_thread_user_unique');
            $table->index(['user_id', 'thread_id'], 'mtp_user_thread');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_thread_participants');
    }
};
