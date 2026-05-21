<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-77 INVITE-DEEPLINK-3: track when a caretaker/landlord invitation is
 * first opened, so the invite funnel can distinguish "sent" from "opened".
 * (tenant_invitations already has viewed_at — mirror it here.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->timestamp('viewed_at')->nullable()->after('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn('viewed_at');
        });
    }
};
