<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-102 OWNER-PORTAL: an owner logs in to a portal (invite-only, like water_client).
 * Widen invitations.role + onboarding_sessions.role to include 'owner', and let an
 * invitation carry the PropertyOwner it provisions a login for.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE invitations MODIFY role ENUM('landlord', 'caretaker', 'tenant', 'water_client', 'owner') NOT NULL DEFAULT 'caretaker'");
        DB::statement("ALTER TABLE onboarding_sessions MODIFY role ENUM('landlord', 'caretaker', 'tenant', 'water_client', 'owner') NOT NULL");

        Schema::table('invitations', function (Blueprint $table): void {
            $table->foreignId('property_owner_id')->nullable()->after('water_connection_id')->constrained('property_owners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('property_owner_id');
        });

        // Narrowing the ENUM errors on any owner rows — clear them first (they belong to
        // this phase) so the rollback is deterministic.
        DB::table('invitations')->where('role', 'owner')->delete();
        DB::table('onboarding_sessions')->where('role', 'owner')->delete();

        DB::statement("ALTER TABLE invitations MODIFY role ENUM('landlord', 'caretaker', 'tenant', 'water_client') NOT NULL DEFAULT 'caretaker'");
        DB::statement("ALTER TABLE onboarding_sessions MODIFY role ENUM('landlord', 'caretaker', 'tenant', 'water_client') NOT NULL");
    }
};
