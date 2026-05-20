<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-66 NPS-SURVEY-1: structured Net Promoter Score responses.
 *
 * `landlord_id` is the TenantScope ownership column (auto-populated by
 * the trait): for a landlord respondent it is their own id, for a
 * tenant/caretaker it is their landlord_id — so a landlord's NPS pane
 * never leaks another landlord's responses.
 *
 * `category` is derived server-side from `score` (0-6 detractor /
 * 7-8 passive / 9-10 promoter) and persisted so the rollup cron can
 * GROUP BY without recomputing the bucket on every read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nps_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->enum('category', ['detractor', 'passive', 'promoter']);
            $table->text('comment')->nullable();
            $table->string('context')->nullable();
            $table->timestamp('prompted_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['landlord_id', 'created_at'], 'nps_landlord_created');
            $table->index(['user_id', 'created_at'], 'nps_user_created');
            $table->index(['category', 'created_at'], 'nps_category_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nps_responses');
    }
};
