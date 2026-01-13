<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();

            $table->string('name');
            $table->string('design')->default('classic');
            $table->boolean('is_default')->default(false);

            // Toggle Fields
            $table->boolean('show_logo')->default(true);
            $table->boolean('show_tax_number')->default(false);
            $table->boolean('show_tenant_id')->default(false);
            $table->boolean('show_unit_details')->default(true);
            $table->boolean('show_lease_reference')->default(false);
            $table->boolean('show_due_date')->default(true);
            $table->boolean('show_late_warning')->default(true);
            $table->boolean('show_bank_details')->default(true);
            $table->boolean('show_footer')->default(true);
            $table->boolean('show_qr_code')->default(false);
            $table->boolean('show_payment_instructions')->default(true);
            $table->boolean('show_arrears_breakdown')->default(true);
            $table->boolean('show_water_details')->default(true);

            // Custom Content
            $table->text('custom_header')->nullable();
            $table->text('custom_footer')->nullable();
            $table->string('primary_color')->default('#4F46E5');
            $table->string('secondary_color')->default('#6B7280');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_templates');
    }
};
