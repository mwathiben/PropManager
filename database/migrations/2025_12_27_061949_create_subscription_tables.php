<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Subscription Plans (system-wide, not tenant-scoped)
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 'Free', 'Basic', 'Pro', 'Enterprise'
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->string('currency')->default('KES');

            // Plan Limits
            $table->integer('max_properties')->default(1);
            $table->integer('max_buildings')->default(2);
            $table->integer('max_units')->default(10);
            $table->integer('max_caretakers')->default(1);
            $table->boolean('water_billing_enabled')->default(false);
            $table->boolean('ocr_enabled')->default(false);
            $table->boolean('reports_enabled')->default(false);
            $table->boolean('bulk_operations_enabled')->default(false);
            $table->boolean('document_storage_enabled')->default(false);
            $table->integer('document_storage_mb')->default(100);
            $table->boolean('email_notifications_enabled')->default(true);
            $table->boolean('sms_notifications_enabled')->default(false);
            $table->boolean('priority_support')->default(false);

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Landlord Subscriptions
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('subscription_plans');

            $table->enum('status', ['active', 'cancelled', 'past_due', 'trialing', 'paused'])->default('trialing');
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->string('paystack_subscription_code')->nullable();
            $table->string('paystack_customer_code')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Subscription Payments (SaaS billing history)
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained();

            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('KES');
            $table->enum('status', ['pending', 'successful', 'failed', 'refunded'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('reference')->unique();
            $table->string('paystack_reference')->nullable();
            $table->json('paystack_response')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // Usage Tracking (for metered features)
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('feature'); // 'properties', 'buildings', 'units', 'documents_mb', 'sms_sent'
            $table->integer('quantity')->default(0);
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamps();

            $table->unique(['user_id', 'feature', 'period_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_records');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
