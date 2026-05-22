<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase-85 DISPUTE-2: extend notifications.type ENUM with 'payment_dispute' so
 * the dispute notification row can persist (mirrors Phase-82's document_expiry).
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
                'document_expiry',
                'payment_dispute'
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
                'new_message',
                'document_expiry'
            ) NOT NULL
        SQL);
    }
};
