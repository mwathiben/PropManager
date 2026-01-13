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
        Schema::create('platform_billing_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('active_billing_model', ['transaction_fee', 'subscription', 'hybrid'])
                ->default('transaction_fee');
            $table->decimal('transaction_fee_percentage', 5, 2)->default(2.50);
            $table->decimal('minimum_fee', 10, 2)->default(50.00);
            $table->decimal('maximum_fee', 10, 2)->nullable();
            $table->enum('fee_bearer', ['landlord', 'platform', 'shared'])->default('landlord');
            $table->decimal('hybrid_subscription_discount', 5, 2)->default(100.00);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_billing_settings');
    }
};
