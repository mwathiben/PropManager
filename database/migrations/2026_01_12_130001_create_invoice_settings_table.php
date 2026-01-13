<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();

            // Business Details
            $table->string('business_name')->nullable();
            $table->text('business_address')->nullable();
            $table->string('business_phone')->nullable();
            $table->string('business_email')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('tax_number')->nullable();

            // Bank Account Details
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('bank_swift_code')->nullable();

            // Invoice Numbering
            $table->string('invoice_prefix')->default('INV');
            $table->unsignedInteger('invoice_next_number')->default(1);
            $table->string('receipt_prefix')->default('RCT');
            $table->unsignedInteger('receipt_next_number')->default(1);
            $table->string('credit_note_prefix')->default('CN');
            $table->unsignedInteger('credit_note_next_number')->default(1);

            // Default Terms
            $table->unsignedSmallInteger('default_due_days')->default(7);
            $table->decimal('late_penalty_percentage', 5, 2)->default(0);
            $table->unsignedSmallInteger('grace_period_days')->default(0);

            // Custom Terms
            $table->text('terms_and_conditions')->nullable();
            $table->text('footer_note')->nullable();

            // Automation Settings
            $table->boolean('auto_generate_enabled')->default(false);
            $table->unsignedTinyInteger('auto_generate_day')->default(1);
            $table->boolean('auto_send_enabled')->default(false);

            $table->timestamps();

            $table->unique('landlord_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_settings');
    }
};
