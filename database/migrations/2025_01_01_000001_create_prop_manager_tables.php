<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update Users Table (Add Roles & Landlord Link)
        Schema::table('users', function (Blueprint $table) {
            // Roles: 'admin', 'landlord', 'caretaker', 'tenant'
            $table->string('role')->default('landlord');
            $table->string('mobile_number')->nullable();

            // Multi-Tenancy: If this is a caretaker/tenant, who owns them?
            $table->foreignId('landlord_id')->nullable()->constrained('users')->onDelete('cascade');

            // Security: National ID & Bank Details (Will be encrypted via Model Casts)
            $table->text('national_id')->nullable();
            $table->text('bank_details')->nullable();
        });

        // 2. Properties Table
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('type'); // 'residential', 'commercial', 'mixed'
            $table->string('address')->nullable();
            $table->timestamps();
        });

        // 3. Buildings Table
        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->integer('total_floors')->default(1);
            $table->integer('units_per_floor')->default(1);
            $table->timestamps();
        });

        // 4. Units Table
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->onDelete('cascade');
            $table->foreignId('landlord_id')->constrained('users'); // Direct link for easier scoping

            $table->string('unit_number'); // "A101"
            $table->integer('floor_number');

            // Status & Pricing
            $table->enum('status', ['vacant', 'occupied', 'maintenance'])->default('vacant');
            $table->decimal('target_rent', 10, 2)->nullable(); // The "Market Price" for this unit

            $table->string('meter_number')->nullable();
            $table->timestamps();
        });

        // 5. Leases Table
        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained();
            $table->foreignId('tenant_id')->constrained('users');
            $table->foreignId('landlord_id')->constrained('users'); // Scope

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->decimal('rent_amount', 10, 2); // Actual agreed price
            $table->decimal('deposit_amount', 10, 2)->default(0);
            $table->decimal('wallet_balance', 10, 2)->default(0); // For prepayments

            $table->boolean('is_active')->default(true);
            $table->string('lease_doc_path')->nullable(); // Private S3 Path
            $table->timestamps();
        });

        // 6. Water Readings Table
        Schema::create('water_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained();
            $table->foreignId('landlord_id')->constrained('users'); // Scope

            $table->date('reading_date');
            $table->decimal('previous_reading', 10, 2);
            $table->decimal('current_reading', 10, 2);
            $table->decimal('consumption', 10, 2);
            $table->decimal('cost', 10, 2);

            $table->boolean('is_invoiced')->default(false); // Lock mechanism
            $table->timestamps();
        });

        // 7. Invoices Table
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained();
            $table->foreignId('landlord_id')->constrained('users'); // Scope

            $table->string('invoice_number')->unique();
            $table->date('due_date');
            $table->date('billing_period_start');

            $table->decimal('rent_due', 10, 2);
            $table->decimal('water_due', 10, 2);
            $table->decimal('arrears', 10, 2)->default(0);
            $table->decimal('total_due', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);

            $table->enum('status', ['draft', 'sent', 'partial', 'paid', 'overdue', 'voided', 'cancelled'])->default('draft');
            $table->timestamps();
        });

        // 8. Payment Methods Table (For Paystack/Internal Switch)
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id'); // The Tenant or Landlord
            $table->string('gateway'); // 'paystack', 'stripe', 'internal'
            $table->string('gateway_token'); // The token/auth code
            $table->string('last_four')->nullable();
            $table->string('card_brand')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('water_readings');
        Schema::dropIfExists('leases');
        Schema::dropIfExists('units');
        Schema::dropIfExists('buildings');
        Schema::dropIfExists('properties');
        // We don't drop users, we just leave the columns
    }
};
