<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-62 CONFLICT-RESOLUTION-1: add `version` to the three mutable
 * resources whose offline replay can race with concurrent edits.
 *
 * Default 1 so existing rows have a starting point. Bumped on every
 * save by the RowVersion trait. POST/PATCH handlers assert If-Match
 * matches the current version (when the header is present); throw
 * WriteConflictException when they don't.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tickets', 'ticket_comments', 'water_readings'] as $table) {
            if (! Schema::hasColumn($table, 'version')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->unsignedInteger('version')->default(1)->after('id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['tickets', 'ticket_comments', 'water_readings'] as $table) {
            if (Schema::hasColumn($table, 'version')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('version');
                });
            }
        }
    }
};
