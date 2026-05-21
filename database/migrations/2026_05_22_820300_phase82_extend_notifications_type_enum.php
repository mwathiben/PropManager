<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase-82 DOC-REMINDERS-2: extend notifications.type ENUM to include the new
 * 'document_expiry' value. Notification::TYPE_DOCUMENT_EXPIRY + TYPE_URGENCY_MAP
 * already reference it; this brings the DB column constraint in line so
 * NotificationService::send can persist the reminder row.
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
                'new_message',
                'document_expiry'
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
                'tenant_invitation',
                'new_message'
            ) NOT NULL
        SQL);
    }
};
