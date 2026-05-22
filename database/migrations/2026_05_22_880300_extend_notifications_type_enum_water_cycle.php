<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase-88 WATER-READING-CYCLE: extend the notifications.type ENUM with the two
 * water-cycle types so rows can persist.
 */
return new class extends Migration
{
    public function up(): void
    {
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
                'payment_dispute',
                'water_reading_due',
                'water_review_due'
            ) NOT NULL
        SQL);
    }

    public function down(): void
    {
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
};
