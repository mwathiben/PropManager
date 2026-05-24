<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-97 WATER-CLIENT-BILLING: the water_bill_due notification type — opt-out
 * preference column + the notifications.type ENUM value (the 3-layer recipe).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table): void {
            if (! Schema::hasColumn('notification_preferences', 'water_bill_due_enabled')) {
                $table->boolean('water_bill_due_enabled')->default(true)->after('water_arrears_enabled');
            }
        });

        DB::statement(<<<'SQL'
            ALTER TABLE notifications MODIFY COLUMN type ENUM(
                'rent_reminder','arrears_notice','invoice','receipt','rent_hike',
                'lease_expiry','lease_renewal','maintenance_notice','general',
                'eviction_notice','caretaker_invitation','tenant_invitation',
                'new_message','document_expiry','payment_dispute',
                'water_reading_due','water_review_due','water_arrears','water_bill_due'
            ) NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE notifications MODIFY COLUMN type ENUM(
                'rent_reminder','arrears_notice','invoice','receipt','rent_hike',
                'lease_expiry','lease_renewal','maintenance_notice','general',
                'eviction_notice','caretaker_invitation','tenant_invitation',
                'new_message','document_expiry','payment_dispute',
                'water_reading_due','water_review_due','water_arrears'
            ) NOT NULL
        SQL);

        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->dropColumn('water_bill_due_enabled');
        });
    }
};
