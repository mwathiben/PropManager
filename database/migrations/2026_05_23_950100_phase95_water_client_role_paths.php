<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-95 WATER-CLIENT-ONBOARDING: the role-path ENUMs were hard [landlord,
 * caretaker,tenant] (Phase-94 review flagged this would 500 a water_client mid-
 * onboarding). Widen invitations.role + onboarding_sessions.role to include
 * water_client, and let an invitation carry the water_connection it provisions.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE invitations MODIFY role ENUM('landlord', 'caretaker', 'tenant', 'water_client') NOT NULL DEFAULT 'caretaker'");
        DB::statement("ALTER TABLE onboarding_sessions MODIFY role ENUM('landlord', 'caretaker', 'tenant', 'water_client') NOT NULL");

        // A water-client invitation has no property — property_id was NOT NULL.
        DB::statement('ALTER TABLE invitations MODIFY property_id BIGINT UNSIGNED NULL');

        Schema::table('invitations', function (Blueprint $table): void {
            $table->foreignId('water_connection_id')->nullable()->after('property_id')->constrained('water_connections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('water_connection_id');
        });

        // Narrowing the ENUM truncates/errors on any water_client rows — clear them
        // first so the rollback is deterministic (these rows belong to this phase).
        DB::table('invitations')->where('role', 'water_client')->delete();
        DB::table('onboarding_sessions')->where('role', 'water_client')->delete();

        DB::statement("ALTER TABLE invitations MODIFY role ENUM('landlord', 'caretaker', 'tenant') NOT NULL DEFAULT 'caretaker'");
        DB::statement("ALTER TABLE onboarding_sessions MODIFY role ENUM('landlord', 'caretaker', 'tenant') NOT NULL");

        // Restore the pre-Phase-95 NOT NULL on property_id (water_client invites, the
        // only rows that needed it nullable, are gone after the delete above).
        DB::statement('ALTER TABLE invitations MODIFY property_id BIGINT UNSIGNED NOT NULL');
    }
};
