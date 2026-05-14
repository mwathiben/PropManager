<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-24 I18N-INFRA-1: per-user locale preference. Nullable — a
 * null value means "no preference", and User::effectiveLocale()
 * falls back to config('app.locale'). Existing rows need no backfill.
 * Sibling of the Phase-18 users.timezone column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
