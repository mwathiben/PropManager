<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-82 DOC-REMINDERS-2: extend the per-type opt-in matrix with the
 * document_expiry type so NotificationService::send can resolve a channel
 * for expiry reminders. Channel-level toggles still gate actual dispatch.
 *
 * Default TRUE so the opt-out is explicit. Backfill via the DEFAULT clause.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            if (! Schema::hasColumn('notification_preferences', 'document_expiry_enabled')) {
                $table->boolean('document_expiry_enabled')
                    ->default(true)
                    ->after('new_message_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            if (Schema::hasColumn('notification_preferences', 'document_expiry_enabled')) {
                $table->dropColumn('document_expiry_enabled');
            }
        });
    }
};
