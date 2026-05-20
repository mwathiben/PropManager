<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-66 ONBOARDING-TOUR-1: server-authoritative per-user tour state.
 * One row per (user, tour_key); status is terminal once completed or
 * dismissed so a tour never re-triggers. current_step is the cursor the
 * client resumes from across devices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_tour_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tour_key', 64);
            $table->unsignedSmallInteger('current_step')->default(0);
            $table->enum('status', ['active', 'completed', 'dismissed'])->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_advanced_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tour_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tour_states');
    }
};
