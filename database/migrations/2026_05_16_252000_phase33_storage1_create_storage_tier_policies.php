<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-33 COST-STORAGE-1: storage_tier_policies — per-disk +
 * per-path-prefix lifecycle rules. storage:tier-policy walks these
 * weekly and emits storage_bytes_by_tier_total gauges; an operator
 * applies actual S3 LIFECYCLE rules manually based on the audit
 * output (we do NOT auto-move objects from the cron — too risky
 * for an audit-cycle feature).
 *
 * Composite unique on (disk_name, path_prefix) — one policy per
 * disk+prefix pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_tier_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('disk_name', 64);
            $table->string('path_prefix', 191);
            $table->unsignedInteger('max_age_days');
            $table->enum('target_tier', ['standard', 'ia', 'glacier', 'deep_archive']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['disk_name', 'path_prefix'], 'stp_disk_prefix_uq');
            $table->index('is_active', 'stp_is_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_tier_policies');
    }
};
