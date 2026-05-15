<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-25 API-AUTH-2: track the IP a Sanctum token was last used
 * from. Sanctum already updates `last_used_at` automatically on
 * every authenticated request; this column adds the second signal a
 * landlord needs to spot a leaked token (last used from an unfamiliar
 * IP = compromise indicator).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }
        if (Schema::hasColumn('personal_access_tokens', 'last_used_ip')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('last_used_ip', 45)->nullable()->after('last_used_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('personal_access_tokens', 'last_used_ip')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn('last_used_ip');
        });
    }
};
