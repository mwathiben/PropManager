<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-34 GROWTH-REFERRAL-1: viral-loop primitives.
 *
 *   users.referral_code  : 8-char unique code shareable on WhatsApp.
 *   referrals            : (referrer, referred, code, status) ledger.
 *
 * No TenantScope on referrals — the table is cross-landlord by
 * design (referrer A introduces referred B, both are landlords).
 *
 * referred_user_id is uniqued so a user can only be referred ONCE —
 * the second referral attempt for the same person is a no-op (the
 * attribution race can only have one winner).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->char('referral_code', 8)->nullable()->unique()->after('email');
        });

        Schema::create('referrals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('referrer_user_id');
            $table->unsignedBigInteger('referred_user_id');
            $table->char('referral_code', 8);
            $table->enum('status', ['pending', 'attributed', 'rewarded', 'expired'])
                ->default('pending');
            $table->timestamp('attributed_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();
            $table->unique('referred_user_id', 'ref_referred_uq');
            $table->index('referrer_user_id', 'ref_referrer_idx');
            $table->index(['status', 'created_at'], 'ref_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['referral_code']);
            $table->dropColumn('referral_code');
        });
    }
};
