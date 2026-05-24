<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-98 WATER-CLIENT-INVOICING-UNIFY: one invoicing system. A water client (a
 * WaterConnection, no lease) is now billed via a real invoice, so invoices.lease_id
 * becomes nullable and an invoice may instead carry a water_connection_id. Exactly
 * one of the two is set. Existing lease invoices keep their lease_id (and its cascade
 * FK) untouched; a null-lease row is simply not affected by a lease delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Nullable lease_id — the existing FK + onDelete(cascade) is preserved (a
        // water-client invoice has lease_id NULL, so no lease delete ever touches it).
        DB::statement('ALTER TABLE invoices MODIFY lease_id BIGINT UNSIGNED NULL');

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('water_connection_id')->nullable()->after('lease_id')
                ->constrained('water_connections')->restrictOnDelete();

            // Idempotency backstop: at most one invoice per connection per period.
            // (Replaces the retired water_client_charges unique. Lease invoices have a
            // NULL water_connection_id, and MySQL treats NULLs as distinct in a unique
            // index, so this never collides on the lease side. Explicitly named to stay
            // under MySQL's 64-char identifier limit.)
            $table->unique(['water_connection_id', 'billing_period_start'], 'inv_water_conn_period_unique');
        });

        // Exactly one billing anchor — a lease OR a water connection, never both/neither.
        // (MySQL 8.0.16+ enforces CHECK; older engines parse-and-ignore — the biller
        // sets exactly one regardless.)
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_lease_xor_water_connection CHECK ((lease_id IS NOT NULL AND water_connection_id IS NULL) OR (lease_id IS NULL AND water_connection_id IS NOT NULL))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT invoices_lease_xor_water_connection');

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('inv_water_conn_period_unique');
            $table->dropConstrainedForeignId('water_connection_id');
        });

        // Water-client invoices (null lease) must be gone before restoring NOT NULL.
        DB::table('invoices')->whereNull('lease_id')->delete();
        DB::statement('ALTER TABLE invoices MODIFY lease_id BIGINT UNSIGNED NOT NULL');
    }
};
