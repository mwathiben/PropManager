<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-71 REPLY-QUOTE: lets a message quote-reply to another message in the
 * SAME thread. Self-referencing FK; nullOnDelete so retracting the quoted
 * message degrades the reply to an un-quoted one rather than cascading.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('reply_to_id')
                ->nullable()
                ->after('sender_id')
                ->constrained('messages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reply_to_id');
        });
    }
};
