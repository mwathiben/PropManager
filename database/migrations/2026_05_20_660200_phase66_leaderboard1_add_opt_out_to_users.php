<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-66 REFERRAL-LEADERBOARD-3: DPA opt-out from the public referral
 * leaderboard. Default false (opted in) — referrers can remove
 * themselves from public display at any time via the toggle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('leaderboard_opt_out')->default(false)->after('acquisition_source');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('leaderboard_opt_out');
        });
    }
};
