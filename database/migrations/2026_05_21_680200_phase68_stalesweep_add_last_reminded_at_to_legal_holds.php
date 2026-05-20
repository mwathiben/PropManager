<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-68 STALE-SWEEP-2: tracks the last stale-hold reminder so the
 * daily sweeper nudges the owning landlord at most once per cooldown
 * window instead of every single day a hold is stale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_holds', function (Blueprint $table) {
            $table->timestamp('last_reminded_at')->nullable()->after('released_by');
        });
    }

    public function down(): void
    {
        Schema::table('legal_holds', function (Blueprint $table) {
            $table->dropColumn('last_reminded_at');
        });
    }
};
