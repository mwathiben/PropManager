<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fix SQLite CHECK constraints for notifications table to support new types and channels.
     */
    public function up(): void
    {
        if (config('database.default') === 'sqlite') {
            // SQLite doesn't support modifying CHECK constraints, so we need to recreate the table
            DB::statement('PRAGMA foreign_keys=off;');

            // Create a new table with the correct CHECK constraints
            DB::statement("
                CREATE TABLE notifications_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    landlord_id INTEGER NOT NULL,
                    recipient_id INTEGER,
                    type VARCHAR CHECK (type IN ('rent_reminder', 'arrears_notice', 'invoice', 'receipt', 'rent_hike', 'lease_expiry', 'lease_renewal', 'maintenance_notice', 'general', 'eviction_notice', 'caretaker_invitation', 'tenant_invitation')) NOT NULL,
                    channel VARCHAR CHECK (channel IN ('email', 'sms', 'whatsapp', 'push', 'in_app')) NOT NULL,
                    subject VARCHAR,
                    message TEXT NOT NULL,
                    data TEXT,
                    status VARCHAR CHECK (status IN ('pending', 'sent', 'failed', 'delivered', 'read')) NOT NULL DEFAULT 'pending',
                    external_id VARCHAR,
                    error_message TEXT,
                    sent_at DATETIME,
                    delivered_at DATETIME,
                    read_at DATETIME,
                    created_at DATETIME,
                    updated_at DATETIME,
                    FOREIGN KEY(landlord_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY(recipient_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");

            // Copy data from old table to new table
            DB::statement('INSERT INTO notifications_new SELECT * FROM notifications');

            // Drop the old table
            DB::statement('DROP TABLE notifications');

            // Rename the new table
            DB::statement('ALTER TABLE notifications_new RENAME TO notifications');

            DB::statement('PRAGMA foreign_keys=on;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting would require recreating with old constraints
        // This is a one-way migration for safety
    }
};
