<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-104 OWNER-REMITTANCE-NOTIFY: two owner-facing notification types
 * (owner_payout_sent, owner_statement_sent) — opt-out preference columns + the
 * notifications.type ENUM values (the 3-layer recipe, mirroring Phase-97).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table): void {
            if (! Schema::hasColumn('notification_preferences', 'owner_payout_sent_enabled')) {
                $table->boolean('owner_payout_sent_enabled')->default(true)->after('water_bill_due_enabled');
            }
            if (! Schema::hasColumn('notification_preferences', 'owner_statement_sent_enabled')) {
                $table->boolean('owner_statement_sent_enabled')->default(true)->after('owner_payout_sent_enabled');
            }
        });

        DB::statement(<<<'SQL'
            ALTER TABLE notifications MODIFY COLUMN type ENUM(
                'rent_reminder','arrears_notice','invoice','receipt','rent_hike',
                'lease_expiry','lease_renewal','maintenance_notice','general',
                'eviction_notice','caretaker_invitation','tenant_invitation',
                'new_message','document_expiry','payment_dispute',
                'water_reading_due','water_review_due','water_arrears','water_bill_due',
                'owner_payout_sent','owner_statement_sent'
            ) NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::table('notifications')->whereIn('type', ['owner_payout_sent', 'owner_statement_sent'])->delete();

        DB::statement(<<<'SQL'
            ALTER TABLE notifications MODIFY COLUMN type ENUM(
                'rent_reminder','arrears_notice','invoice','receipt','rent_hike',
                'lease_expiry','lease_renewal','maintenance_notice','general',
                'eviction_notice','caretaker_invitation','tenant_invitation',
                'new_message','document_expiry','payment_dispute',
                'water_reading_due','water_review_due','water_arrears','water_bill_due'
            ) NOT NULL
        SQL);

        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->dropColumn(['owner_payout_sent_enabled', 'owner_statement_sent_enabled']);
        });
    }
};
