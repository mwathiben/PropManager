<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-90: the water_arrears notification type — opt-out pref column + type ENUM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            if (! Schema::hasColumn('notification_preferences', 'water_arrears_enabled')) {
                $table->boolean('water_arrears_enabled')->default(true)->after('water_review_due_enabled');
            }
        });

        DB::statement(<<<'SQL'
            ALTER TABLE notifications MODIFY COLUMN type ENUM(
                'rent_reminder','arrears_notice','invoice','receipt','rent_hike',
                'lease_expiry','lease_renewal','maintenance_notice','general',
                'eviction_notice','caretaker_invitation','tenant_invitation',
                'new_message','document_expiry','payment_dispute',
                'water_reading_due','water_review_due','water_arrears'
            ) NOT NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn('water_arrears_enabled');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE notifications MODIFY COLUMN type ENUM(
                'rent_reminder','arrears_notice','invoice','receipt','rent_hike',
                'lease_expiry','lease_renewal','maintenance_notice','general',
                'eviction_notice','caretaker_invitation','tenant_invitation',
                'new_message','document_expiry','payment_dispute',
                'water_reading_due','water_review_due'
            ) NOT NULL
        SQL);
    }
};
