<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-101 OWNER-FOUNDATION: link a property to its owner (the landlord/PM still
 * MANAGES it; the owner is who it's managed FOR). One owner per property, nullable
 * (unassigned until the PM sets it); the owner is cleared, not the property, on delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->foreignId('property_owner_id')->nullable()->after('landlord_id')
                ->constrained('property_owners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('property_owner_id');
        });
    }
};
