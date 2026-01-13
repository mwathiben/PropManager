<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'push' to channel enum and 'eviction_notice' to type enum
        // SQLite doesn't support ENUM modification, so we handle this differently
        if (config('database.default') === 'sqlite') {
            // For SQLite, the column is stored as TEXT, so no modification needed
            // The model will handle validation
        } else {
            // For MySQL/PostgreSQL
            DB::statement("ALTER TABLE notifications MODIFY COLUMN channel ENUM('email', 'sms', 'whatsapp', 'push') NOT NULL");
            DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM('rent_reminder', 'arrears_notice', 'invoice', 'receipt', 'rent_hike', 'lease_expiry', 'lease_renewal', 'maintenance_notice', 'general', 'eviction_notice') NOT NULL");
        }

        // Add push_enabled to notification_preferences
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('push_enabled')->default(false)->after('whatsapp_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn('push_enabled');
        });

        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE notifications MODIFY COLUMN channel ENUM('email', 'sms', 'whatsapp') NOT NULL");
            DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM('rent_reminder', 'arrears_notice', 'invoice', 'receipt', 'rent_hike', 'lease_expiry', 'lease_renewal', 'maintenance_notice', 'general') NOT NULL");
        }
    }
};
