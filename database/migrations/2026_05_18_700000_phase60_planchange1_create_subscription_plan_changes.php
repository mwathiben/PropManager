<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-60 PLAN-CHANGE-2: audit trail for plan switches. Every
 * /subscription/change writes a row here so support can answer
 * "when did this landlord upgrade from Starter to Pro?".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('from_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->foreignId('to_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->string('proration_behaviour', 32)->default('create_prorations');
            $table->boolean('stripe_succeeded')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'created_at'], 'spc_sub_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_changes');
    }
};
