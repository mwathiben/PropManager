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
        // 1. Verification Templates - Configurable checklists per property
        Schema::create('verification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g., "Standard Residential", "Commercial"
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['landlord_id', 'is_default']);
        });

        // 2. Verification Items - Items within a verification template
        Schema::create('verification_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_template_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g., "National ID", "Employment Letter"
            $table->string('document_type')->nullable(); // Maps to Document model types
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('verification_template_id');
        });

        // 3. Tenant Verifications - Actual verification status per tenant/lease
        Schema::create('tenant_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verification_item_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'submitted', 'verified', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['lease_id', 'verification_item_id']);
            $table->index(['landlord_id', 'status']);
        });

        // 4. Move-Outs - Move-out workflow tracking
        Schema::create('move_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->date('notice_date');
            $table->date('intended_move_out_date');
            $table->date('actual_move_out_date')->nullable();
            $table->enum('status', [
                'notice_given',
                'inspection_pending',
                'inspection_complete',
                'settlement_pending',
                'completed',
                'cancelled',
            ])->default('notice_given');
            $table->text('inspection_notes')->nullable();
            $table->decimal('deposit_held', 10, 2)->default(0);
            $table->decimal('total_deductions', 10, 2)->default(0);
            $table->decimal('arrears_balance', 10, 2)->default(0);
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->enum('settlement_method', ['cash', 'bank_transfer', 'mobile_money'])->nullable();
            $table->string('settlement_reference')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['landlord_id', 'status']);
            $table->index('lease_id');
        });

        // 5. Move-Out Deductions - Individual deduction items
        Schema::create('move_out_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('move_out_id')->constrained()->cascadeOnDelete();
            $table->string('description'); // e.g., "Broken window", "Wall damage"
            $table->decimal('amount', 10, 2);
            $table->text('notes')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();

            $table->index('move_out_id');
        });

        // 6. Move-Out Inspection Items - Configurable inspection checklist
        Schema::create('move_out_inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('name'); // e.g., "Kitchen clean", "Walls undamaged"
            $table->string('category'); // e.g., "Kitchen", "Bathroom", "General"
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['landlord_id', 'is_active']);
        });

        // 7. Move-Out Inspection Results - Results per item per move-out
        Schema::create('move_out_inspection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('move_out_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_item_id')->constrained('move_out_inspection_items')->cascadeOnDelete();
            $table->enum('result', ['pass', 'fail', 'not_applicable'])->default('not_applicable');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['move_out_id', 'inspection_item_id'], 'move_out_inspection_unique');
        });

        // 8. Tenant Notes - Private landlord notes about tenants
        Schema::create('tenant_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['landlord_id', 'tenant_id']);
        });

        // 9. Emergency Contacts - Next of kin info
        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['landlord_id', 'tenant_id']);
        });

        // 10. Tenant Activities - Activity log for tenant lifecycle
        Schema::create('tenant_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->string('type'); // 'lease_created', 'rent_adjusted', 'document_uploaded', etc.
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['landlord_id', 'tenant_id']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_activities');
        Schema::dropIfExists('emergency_contacts');
        Schema::dropIfExists('tenant_notes');
        Schema::dropIfExists('move_out_inspection_results');
        Schema::dropIfExists('move_out_inspection_items');
        Schema::dropIfExists('move_out_deductions');
        Schema::dropIfExists('move_outs');
        Schema::dropIfExists('tenant_verifications');
        Schema::dropIfExists('verification_items');
        Schema::dropIfExists('verification_templates');
    }
};
