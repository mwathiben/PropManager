<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-63 INBOX-NOTIFY-2: extend the per-type opt-in matrix with the
 * new_message type. Existing channel-level toggles (sms_enabled,
 * whatsapp_enabled etc.) continue to gate the actual dispatch, so a
 * user who is globally opted out of paid channels won't receive a
 * new_message via SMS even with the type enabled.
 *
 * Default TRUE so the opt-out is explicit. Backfill happens via the
 * column DEFAULT clause — no separate UPDATE pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            if (! Schema::hasColumn('notification_preferences', 'new_message_enabled')) {
                $table->boolean('new_message_enabled')
                    ->default(true)
                    ->after('lifecycle_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            if (Schema::hasColumn('notification_preferences', 'new_message_enabled')) {
                $table->dropColumn('new_message_enabled');
            }
        });
    }
};
