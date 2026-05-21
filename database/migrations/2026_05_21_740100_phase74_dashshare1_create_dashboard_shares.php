<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-74 DASH-SHARE: a time-boxed, revocable public share of a landlord
 * dashboard. Mirrors report_shares (Phase 73) — the public view route is
 * signed-gated and runs the dashboard with this row's OWN landlord_id, never a
 * request param.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('landlord_dashboard_id')->constrained('landlord_dashboards')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();

            $table->index(['landlord_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_shares');
    }
};
