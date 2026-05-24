<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-103 OWNER-PAYOUTS: a record of money the property manager has actually remitted to
 * an owner. The owner's running balance is derived (lifetime statement net minus the sum of
 * non-voided payouts) — these rows are the only real money-movement records. Money records
 * are voided (voided_at), never hard-deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_owner_id')->constrained('property_owners')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('KES');
            $table->date('paid_on');
            $table->string('method'); // bank_transfer | mpesa | cheque | cash | other
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['landlord_id', 'property_owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_payouts');
    }
};
