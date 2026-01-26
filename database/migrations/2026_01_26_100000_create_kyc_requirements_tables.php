<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // KYC Requirements - Configurable per building or global (landlord_id NULL = platform defaults)
        Schema::create('kyc_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('building_id')->nullable()->constrained()->cascadeOnDelete();

            // Requirement definition
            $table->string('requirement_type', 50); // e.g., 'selfie', 'national_id', 'signed_lease'
            $table->string('label');
            $table->text('description')->nullable();

            // Configuration flags
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['landlord_id', 'building_id', 'is_active']);
            $table->index(['building_id', 'is_active']);

            // Prevent duplicate requirement types per scope
            $table->unique(['landlord_id', 'building_id', 'requirement_type'], 'kyc_req_unique_type');
        });

        // Tenant KYC Submissions - Track submission and review workflow
        Schema::create('tenant_kyc_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained('kyc_requirements')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();

            // Submission data (for non-document types like text fields)
            $table->string('submission_value')->nullable();

            // Review workflow
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index(['user_id', 'status']);
            $table->index(['landlord_id', 'status']);
            $table->index('requirement_id');

            // Each tenant can only have one submission per requirement
            $table->unique(['user_id', 'requirement_id'], 'kyc_sub_unique_per_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_kyc_submissions');
        Schema::dropIfExists('kyc_requirements');
    }
};
