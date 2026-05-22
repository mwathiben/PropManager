<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-85 REFUND-RETRY-2: bounded, idempotent retry of failed refunds.
 * retry_count caps automatic re-attempts; needs_review flags refunds that failed
 * AFTER the gateway already created a refund (have a gateway reference) — those
 * must never be auto-re-called (double-refund risk) and need a human.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->unsignedSmallInteger('retry_count')->default(0)->after('status');
            $table->boolean('needs_review')->default(false)->after('retry_count');
            $table->index(['status', 'needs_review', 'retry_count'], 'refunds_retry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropIndex('refunds_retry_idx');
            $table->dropColumn(['retry_count', 'needs_review']);
        });
    }
};
