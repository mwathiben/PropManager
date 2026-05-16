<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-29 WF-CI-2: workflow_runs_log — observability table that
 * records every workflow firing across all Phase-29 commands and
 * listeners. Answers "why did this tenant receive this notification?"
 * without tailing schedule.log. workflow:health runs nightly to
 * detect silent failures (zero firings in the last 24h for a
 * scheduler that should have run).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_runs_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('workflow_name', 64);
            $table->string('target_type', 120)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('action', 64);
            $table->json('metadata')->nullable();
            $table->timestamp('fired_at');
            $table->timestamps();
            $table->index(['landlord_id', 'workflow_name', 'fired_at'], 'wrl_landlord_wf_fired_idx');
            $table->index(['target_type', 'target_id'], 'wrl_target_idx');
            $table->index(['workflow_name', 'fired_at'], 'wrl_wf_fired_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_runs_log');
    }
};
