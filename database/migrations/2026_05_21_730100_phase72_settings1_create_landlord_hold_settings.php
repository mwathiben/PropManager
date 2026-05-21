<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-72 HOLD-SETTINGS: per-landlord overrides of the Phase-68 global config
 * (stale window, reminder cooldown), plus a matter-reference format, reminder
 * recipients, and the opt-in auto-hold-on-eviction rule. One row per landlord;
 * a NULL column means "fall back to config".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landlord_hold_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('stale_after_days')->nullable();
            $table->unsignedSmallInteger('reminder_cooldown_days')->nullable();
            $table->string('matter_reference_format')->nullable();
            $table->json('reminder_recipients')->nullable();
            $table->boolean('auto_hold_on_eviction')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landlord_hold_settings');
    }
};
