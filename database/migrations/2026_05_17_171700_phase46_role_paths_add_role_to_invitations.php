<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-46 ROLE-PATHS-1: invitations table gains a role column so
 * RegisteredUserController can resolve the invitee's role at signup
 * time. Default 'caretaker' matches the historical semantic (the
 * invitations table was built for caretaker-to-property linkage;
 * tenants use the separate tenant_invitations table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->enum('role', ['landlord', 'caretaker', 'tenant'])
                ->default('caretaker')
                ->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};
