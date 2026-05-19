<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-63 INBOX-MOD-2: per-landlord override for the platform
 * default 7-year message retention (Kenya DPA aligned). NULL = use
 * config('inbox.retention.default_days', 2557).
 *
 * Lives on `users` because landlords ARE users — adding a fresh
 * landlord_settings table would be more correct architecturally but
 * is a heavier refactor; this column matches the existing convention
 * of single-knob landlord-level overrides (kyc_completed_at,
 * payment_gateway_preference, acquisition_source).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'message_retention_days')) {
                $table->unsignedInteger('message_retention_days')
                    ->nullable()
                    ->after('acquisition_source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'message_retention_days')) {
                $table->dropColumn('message_retention_days');
            }
        });
    }
};
