<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'in_app' to channel enum and invitation types to type enum
        // SQLite doesn't support ENUM modification, so we handle this differently
        if (config('database.default') === 'sqlite') {
            // For SQLite, the column is stored as TEXT, so no modification needed
            // The model will handle validation
        } else {
            // For MySQL/PostgreSQL
            DB::statement("ALTER TABLE notifications MODIFY COLUMN channel ENUM('email', 'sms', 'whatsapp', 'push', 'in_app') NOT NULL");
            DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM('rent_reminder', 'arrears_notice', 'invoice', 'receipt', 'rent_hike', 'lease_expiry', 'lease_renewal', 'maintenance_notice', 'general', 'eviction_notice', 'caretaker_invitation', 'tenant_invitation') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE notifications MODIFY COLUMN channel ENUM('email', 'sms', 'whatsapp', 'push') NOT NULL");
            DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM('rent_reminder', 'arrears_notice', 'invoice', 'receipt', 'rent_hike', 'lease_expiry', 'lease_renewal', 'maintenance_notice', 'general', 'eviction_notice') NOT NULL");
        }
    }
};
