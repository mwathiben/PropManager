<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-29 WF-RENT-REMIND-1: per-landlord rent reminder cadence policies.
 *
 * offsets_json holds a JSON array of signed integers (negative = days
 * before due_date, positive = days after). The cadence_template enum
 * selects one of the standard templates; 'custom' uses offsets_json
 * verbatim. RentReminderPolicy::resolveOffsets() returns the integer
 * array used by RentRemindersDispatch.
 *
 * Default policy per landlord is_default=true (used when a lease's
 * reminder_tier has no exact match — fallback path).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rent_reminder_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->enum('cadence_template', ['standard', 'aggressive', 'lenient', 'custom'])
                ->default('standard');
            $table->json('offsets_json')->nullable();
            $table->json('channels')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['landlord_id', 'cadence_template', 'is_active'], 'rrp_landlord_template_active_idx');
            $table->index(['landlord_id', 'is_default'], 'rrp_landlord_default_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_reminder_policies');
    }
};
