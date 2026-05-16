<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-32 SRE-INCIDENT-1: operational incidents (non-security).
 * SecurityIncident (Phase-13) is reserved for ATTACKS (failed-login
 * burst, webhook signature flood, large export). Operational incidents
 * cover OUTAGES + DEGRADATIONS (queue wedge, DB slowdown, Daraja
 * downtime). Without this separation, ops dashboards mix security
 * paging (legal SLA) with operational paging (best-effort) and noise
 * dominates.
 *
 * sev1-4 mirrors PagerDuty's severity scale; status machine is
 * open -> investigating -> mitigated -> resolved (forward-only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('severity', ['sev1', 'sev2', 'sev3', 'sev4']);
            $table->enum('status', ['open', 'investigating', 'mitigated', 'resolved'])->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('mitigated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('affected_services')->nullable();
            $table->text('summary')->nullable();
            $table->text('root_cause')->nullable();
            $table->string('post_mortem_url', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'severity'], 'oi_status_sev_idx');
            $table->index('opened_at', 'oi_opened_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_incidents');
    }
};
