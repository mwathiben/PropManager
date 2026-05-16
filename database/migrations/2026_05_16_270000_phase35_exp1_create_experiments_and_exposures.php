<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-35 PLATFORM-EXP-1: A/B experiments registry + sticky
 * exposure audit.
 *
 * `experiments` — declarative experiment definition. variants is
 * a JSON array of {key, weight} (weights sum to 100). The variant
 * with key='control' is the implicit default + the one returned
 * when an experiment is paused or concluded without a winner.
 *
 * `experiment_exposures` — append-only ledger keyed (user_id,
 * experiment_key) unique. Sticky: once a user gets variant X, they
 * always get variant X for that experiment, even if weights change
 * mid-flight. Recorded at the moment of FIRST exposure so funnel
 * attribution joins work cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiments', function (Blueprint $table): void {
            $table->id();
            $table->string('experiment_key', 64)->unique();
            $table->string('name');
            $table->enum('status', ['draft', 'running', 'paused', 'concluded'])->default('draft');
            $table->json('variants');
            $table->string('winning_variant_key', 32)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index('status', 'exp_status_idx');
        });

        Schema::create('experiment_exposures', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('experiment_key', 64);
            $table->string('variant_key', 32);
            $table->timestamp('fired_at');
            $table->unique(['user_id', 'experiment_key'], 'exp_user_key_uq');
            $table->index(['experiment_key', 'variant_key'], 'exp_key_variant_idx');
            $table->index('fired_at', 'exp_fired_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiment_exposures');
        Schema::dropIfExists('experiments');
    }
};
