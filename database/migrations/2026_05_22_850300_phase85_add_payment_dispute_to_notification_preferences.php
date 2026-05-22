<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-85 DISPUTE-2: per-type opt-in for the payment_dispute notification so
 * NotificationService::send can resolve a channel. Default TRUE — disputes are
 * money at risk; opting out is explicit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            if (! Schema::hasColumn('notification_preferences', 'payment_dispute_enabled')) {
                $table->boolean('payment_dispute_enabled')
                    ->default(true)
                    ->after('document_expiry_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            if (Schema::hasColumn('notification_preferences', 'payment_dispute_enabled')) {
                $table->dropColumn('payment_dispute_enabled');
            }
        });
    }
};
