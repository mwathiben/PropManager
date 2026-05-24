<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase-98 WATER-CLIENT-INVOICING-UNIFY: a water-client invoice has no lease, so a
 * payment (and its receipt) recorded against one carries lease_id = NULL. Make both
 * columns nullable. The existing foreign keys are preserved (raw MODIFY keeps the FK
 * in MySQL); a NULL lease_id simply has no referent.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE payments MODIFY lease_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE receipts MODIFY lease_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // Water-client payments/receipts (null lease) must be gone before restoring NOT NULL.
        DB::table('receipts')->whereNull('lease_id')->delete();
        DB::table('payments')->whereNull('lease_id')->delete();
        DB::statement('ALTER TABLE payments MODIFY lease_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE receipts MODIFY lease_id BIGINT UNSIGNED NOT NULL');
    }
};
