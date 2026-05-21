<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-73 SCHEDULED-DEPTH: let a landlord pause a schedule without deleting
 * it. The send cron skips rows with a non-null paused_at; resuming recomputes
 * next_due_at so a paused stretch doesn't fire a backlog of catch-up sends.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_reports', function (Blueprint $table) {
            $table->timestamp('paused_at')->nullable()->after('last_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_reports', function (Blueprint $table) {
            $table->dropColumn('paused_at');
        });
    }
};
