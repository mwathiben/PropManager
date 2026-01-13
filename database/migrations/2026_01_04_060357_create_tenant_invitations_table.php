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
        Schema::create('tenant_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->foreignId('existing_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 64)->unique();

            // Lease terms
            $table->decimal('rent_amount', 10, 2);
            $table->decimal('service_charge', 10, 2)->default(0);
            $table->decimal('deposit_amount', 10, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();

            // Tenant info (optional, can be filled by tenant on acceptance)
            $table->string('tenant_name')->nullable();
            $table->string('tenant_phone')->nullable();
            $table->string('tenant_id_number')->nullable();

            // Status tracking
            $table->enum('status', ['pending', 'accepted', 'declined', 'expired'])->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');

            $table->timestamps();

            // Indexes for common queries
            $table->index(['email', 'status']);
            $table->index(['landlord_id', 'status']);
            $table->index(['unit_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_invitations');
    }
};
