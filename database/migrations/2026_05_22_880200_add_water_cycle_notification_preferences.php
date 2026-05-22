<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-88 WATER-READING-CYCLE: opt-out columns for the two new notification
 * types (default true — explicit opt-out, matching the existing pattern). Without
 * these, NotificationService resolves no channel and silently drops the message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            if (! Schema::hasColumn('notification_preferences', 'water_reading_due_enabled')) {
                $table->boolean('water_reading_due_enabled')->default(true)->after('payment_dispute_enabled');
            }
            if (! Schema::hasColumn('notification_preferences', 'water_review_due_enabled')) {
                $table->boolean('water_review_due_enabled')->default(true)->after('water_reading_due_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn(['water_reading_due_enabled', 'water_review_due_enabled']);
        });
    }
};
