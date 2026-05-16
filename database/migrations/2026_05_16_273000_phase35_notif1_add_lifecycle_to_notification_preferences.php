<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-35 PLATFORM-NOTIF-1: extend the Phase-28 NotificationPreference
 * type matrix with a 'lifecycle' channel covering the 4 Phase-34
 * landlord-facing campaign Mailables (TrialEnding, DunningReminder,
 * Winback, ActivationNudge).
 *
 * Default true — landlord is paying us, opt-out is explicit. The
 * landlord-facing /api/notifications/preferences endpoint
 * (PLATFORM-NOTIF-2) lets them flip it off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->boolean('lifecycle_enabled')->default(true)->after('tenant_invitation_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->dropColumn('lifecycle_enabled');
        });
    }
};
