<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archived_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_payment_id')->unique();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('lease_id')->nullable();
            $table->unsignedBigInteger('landlord_id')->index();
            $table->unsignedBigInteger('payout_account_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('KES');
            $table->string('payment_method', 50);
            $table->date('payment_date');
            $table->string('reference')->nullable();
            $table->string('paystack_reference')->nullable();
            $table->string('paystack_split_code')->nullable();
            $table->boolean('is_split_payment')->default(false);
            $table->string('mpesa_transaction_id', 50)->nullable();
            $table->string('mpesa_checkout_request_id', 100)->nullable();
            $table->string('intasend_transaction_id', 50)->nullable();
            $table->string('intasend_reference', 100)->nullable();
            $table->string('bank_code', 20)->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_transaction_id')->nullable();
            $table->datetime('bank_transaction_date')->nullable();
            $table->string('bank_reference')->nullable();
            $table->string('reconciliation_status', 20)->nullable();
            $table->datetime('reconciliation_matched_at')->nullable();
            $table->boolean('is_voided')->default(false);
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('original_created_at');
            $table->timestamp('original_updated_at');
            $table->timestamp('archived_at');
            $table->json('related_data')->nullable();
            $table->timestamps();

            $table->index('payment_date');
            $table->index('archived_at');
            $table->index(['landlord_id', 'payment_date']);
        });

        DB::statement('
            CREATE OR REPLACE VIEW all_payments AS
            SELECT
                id,
                invoice_id,
                lease_id,
                landlord_id,
                amount,
                currency,
                payment_method,
                payment_date,
                reference,
                is_voided,
                created_at,
                updated_at,
                0 as is_archived
            FROM payments
            UNION ALL
            SELECT
                original_payment_id as id,
                invoice_id,
                lease_id,
                landlord_id,
                amount,
                currency,
                payment_method,
                payment_date,
                reference,
                is_voided,
                original_created_at as created_at,
                original_updated_at as updated_at,
                1 as is_archived
            FROM archived_payments
        ');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS all_payments');
        Schema::dropIfExists('archived_payments');
    }
};
