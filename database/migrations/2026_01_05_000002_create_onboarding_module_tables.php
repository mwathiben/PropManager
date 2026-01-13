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
        // 1. Landlord Profiles - Extended landlord information
        Schema::create('landlord_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('company_name')->nullable();
            $table->string('business_registration_number')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('Kenya');
            $table->string('tax_id')->nullable();
            $table->string('website')->nullable();
            $table->timestamps();
        });

        // 2. Onboarding Progress - Save wizard state
        Schema::create('onboarding_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->integer('current_step')->default(1);
            $table->integer('total_steps')->default(8);
            $table->json('step_data')->nullable(); // Store partial form data per step
            $table->json('completed_steps')->nullable(); // Array of completed step numbers
            $table->boolean('is_complete')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // 3. Payment Configurations - Landlord payment settings
        Schema::create('payment_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->decimal('default_rent', 10, 2)->nullable();
            $table->enum('water_billing_type', ['consumption', 'flat_rate', 'none'])->default('consumption');
            $table->decimal('flat_water_rate', 10, 2)->nullable();
            $table->decimal('water_unit_rate', 10, 2)->default(150); // KES per unit
            $table->json('accepted_payment_methods')->nullable(); // ['cash', 'mobile_money', 'bank_transfer', 'paystack']
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('mpesa_paybill')->nullable();
            $table->string('mpesa_account_name')->nullable();
            $table->boolean('paystack_enabled')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_configurations');
        Schema::dropIfExists('onboarding_progress');
        Schema::dropIfExists('landlord_profiles');
    }
};
