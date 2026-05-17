<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-45 PAY-PLAN-MOD-1/2/3: extend payment_plans.status with
 * 'modified_pending' + create payment_plan_modifications audit table.
 *
 * Phase 28 shipped requested → approved | rejected → completed | defaulted.
 * Phase 45 adds modified_pending — tenant proposes a new installment
 * schedule after approval; landlord re-approves or rejects.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE payment_plans MODIFY COLUMN status ENUM('requested','approved','rejected','modified_pending','completed','defaulted') NOT NULL DEFAULT 'requested'"
        );

        Schema::create('payment_plan_modifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->json('original_installments');
            $table->json('proposed_installments');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('landlord_response')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['payment_plan_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_plan_modifications');

        DB::statement(
            "ALTER TABLE payment_plans MODIFY COLUMN status ENUM('requested','approved','rejected','completed','defaulted') NOT NULL DEFAULT 'requested'"
        );
    }
};
