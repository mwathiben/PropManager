<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-28 TENANT-PAY-1: payment plans + installments.
 *
 * payment_plans
 *   - landlord_id + tenant_id + invoice_id (single plan per invoice
 *     while active enforced by unique partial index emulation via
 *     application-layer check in PaymentPlanRequestController)
 *   - status state machine: requested → approved | rejected,
 *     approved progresses to completed via installment payments,
 *     defaulted when an installment misses due_date by >7d
 *   - amounts in BIGINT cents to match Phase-17 Money primitives
 *
 * payment_plan_installments
 *   - FK CASCADE on payment_plan_id so deleting a draft plan also
 *     drops its installments
 *   - paid_amount accumulates partial installment payments
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedBigInteger('total_amount_cents');
            $table->enum('status', ['requested', 'approved', 'rejected', 'completed', 'defaulted'])
                ->default('requested');
            $table->text('reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['invoice_id']);
            $table->index(['landlord_id', 'status']);
        });

        Schema::create('payment_plan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_plan_id')->constrained('payment_plans')->cascadeOnDelete();
            $table->date('due_date');
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('paid_amount_cents')->default(0);
            $table->enum('status', ['pending', 'paid', 'defaulted'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['payment_plan_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_plan_installments');
        Schema::dropIfExists('payment_plans');
    }
};
