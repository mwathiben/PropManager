<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-56 COHORT-BY-SOURCE-1: capture how each user found PropManager so
 * cohort analysis can partition retention curves by acquisition source.
 *
 * Enum values:
 *   - organic    : direct signup with no invitation/referral context
 *   - referral   : signup followed a referral_code in the session
 *   - paid       : signup attributed to a paid acquisition channel
 *                  (no automatic write today — operator sets it later)
 *   - invitation : signup consumed an invitation token
 *   - unknown    : default for pre-Phase-56 rows
 *
 * Backfill heuristic: existing users get 'invitation' when any
 * invitations row resolves to them, 'referral' when any referrals row
 * points to them as referred, otherwise 'unknown' (NOT 'organic' — we
 * can't retroactively prove organic origin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('acquisition_source', [
                'organic',
                'referral',
                'paid',
                'invitation',
                'unknown',
            ])->default('unknown')->after('locale');

            $table->index('acquisition_source', 'users_acquisition_source_idx');
        });

        if (Schema::hasTable('invitations') && Schema::hasColumn('invitations', 'email')) {
            DB::statement(
                "UPDATE users SET acquisition_source = 'invitation' "
                .'WHERE email IN (SELECT email FROM invitations WHERE accepted_at IS NOT NULL)'
            );
        }

        if (Schema::hasTable('referrals')) {
            DB::statement(
                "UPDATE users SET acquisition_source = 'referral' "
                .'WHERE id IN (SELECT referred_user_id FROM referrals) '
                ."AND acquisition_source = 'unknown'"
            );
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_acquisition_source_idx');
            $table->dropColumn('acquisition_source');
        });
    }
};
