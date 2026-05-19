<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase-63 INBOX-NOTIFY-2: extend notifications.type ENUM to include
 * the new 'new_message' value introduced for the inbox-fallback
 * pipeline. The Notification::TYPES const + TYPE_URGENCY_MAP already
 * reference it; this migration brings the DB column constraint in
 * line so NotificationService::send can persist a row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('database.default') === 'sqlite') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE notifications MODIFY COLUMN type ENUM(
                'rent_reminder',
                'arrears_notice',
                'invoice',
                'receipt',
                'rent_hike',
                'lease_expiry',
                'lease_renewal',
                'maintenance_notice',
                'general',
                'eviction_notice',
                'caretaker_invitation',
                'tenant_invitation',
                'new_message'
            ) NOT NULL
        SQL);
    }

    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE notifications MODIFY COLUMN type ENUM(
                'rent_reminder',
                'arrears_notice',
                'invoice',
                'receipt',
                'rent_hike',
                'lease_expiry',
                'lease_renewal',
                'maintenance_notice',
                'general',
                'eviction_notice',
                'caretaker_invitation',
                'tenant_invitation'
            ) NOT NULL
        SQL);
    }
};
