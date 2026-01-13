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
        Schema::create('landlord_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->enum('provider', ['paystack', 'flutterwave'])->default('paystack');
            $table->string('subaccount_code')->nullable();
            $table->enum('account_type', ['bank', 'mobile_money'])->default('bank');
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('bank_code')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('business_name');
            $table->string('settlement_bank')->nullable();
            $table->decimal('percentage_charge', 5, 2)->nullable();
            $table->decimal('flat_charge', 10, 2)->nullable();
            $table->enum('verification_status', ['pending', 'verified', 'rejected', 'suspended'])
                ->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['landlord_id', 'subaccount_code']);
            $table->index(['provider', 'verification_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landlord_payout_accounts');
    }
};
