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
        Schema::create('tenant_payment_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending_payment', 'payment_submitted', 'payment_verified', 'rejected'])
                ->default('pending_payment');
            $table->decimal('deposit_required', 10, 2)->default(0);
            $table->decimal('first_rent_required', 10, 2)->default(0);
            $table->decimal('other_charges', 10, 2)->default(0);
            $table->string('other_charges_description')->nullable();
            $table->decimal('total_required', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['landlord_id', 'status']);
            $table->index('lease_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_payment_verifications');
    }
};
