<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-13 DPA-4: Article 18 right to restriction of processing.
 * GDPR Article 18 + Kenya DPA Section 26(d) grant the right to pause
 * processing while accuracy is contested or while a deletion
 * decision is pending. Before this column, PropManager had erasure
 * (Article 17) + export (Article 20) but no pause option.
 *
 * restricted_at is the canonical signal:
 *   - NULL          → not restricted, normal processing
 *   - timestamp     → restricted; read-only mode active since then
 *
 * restriction_reason is operator/audit context for when the
 * regulator asks "why was processing restricted on date X?".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('restricted_at')->nullable();
            $table->string('restriction_reason', 500)->nullable();
            $table->index('restricted_at', 'users_restricted_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_restricted_at_idx');
            $table->dropColumn(['restricted_at', 'restriction_reason']);
        });
    }
};
