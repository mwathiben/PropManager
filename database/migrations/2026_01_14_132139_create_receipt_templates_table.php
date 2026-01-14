<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();

            $table->string('name');
            $table->string('design')->default('classic');
            $table->boolean('is_default')->default(false);

            // Header Elements
            $table->boolean('show_logo')->default(true);
            $table->boolean('show_receipt_number')->default(true);
            $table->boolean('show_payment_date')->default(true);

            // Payment Information
            $table->boolean('show_payment_method')->default(true);
            $table->boolean('show_transaction_reference')->default(true);
            $table->boolean('show_amount_breakdown')->default(false);

            // Tenant Information
            $table->boolean('show_tenant_name')->default(true);
            $table->boolean('show_tenant_email')->default(true);
            $table->boolean('show_tenant_phone')->default(false);

            // Property Information
            $table->boolean('show_unit_details')->default(true);
            $table->boolean('show_building_name')->default(true);

            // Invoice Information
            $table->boolean('show_invoice_details')->default(true);
            $table->boolean('show_invoice_breakdown')->default(false);
            $table->boolean('show_balance_after_payment')->default(true);

            // Footer Elements
            $table->boolean('show_thank_you_message')->default(true);
            $table->boolean('show_qr_code')->default(false);
            $table->boolean('show_footer')->default(true);

            // Custom Content
            $table->text('custom_header')->nullable();
            $table->text('custom_footer')->nullable();
            $table->string('thank_you_message')->default('Thank you for your payment!');

            // Colors
            $table->string('primary_color')->default('#059669');
            $table->string('secondary_color')->default('#10B981');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_templates');
    }
};
