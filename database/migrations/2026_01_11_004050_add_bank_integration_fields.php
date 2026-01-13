<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add bank-specific fields to payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->string('bank_code', 20)->nullable()->after('payment_method');
            $table->string('bank_account_number')->nullable()->after('bank_code');
            $table->string('bank_transaction_id')->nullable()->after('bank_account_number');
            $table->datetime('bank_transaction_date')->nullable()->after('bank_transaction_id');
            $table->string('bank_reference')->nullable()->after('bank_transaction_date');
            $table->enum('reconciliation_status', ['pending', 'matched', 'disputed', 'reversed'])
                ->default('matched')
                ->after('bank_reference');
            $table->datetime('reconciliation_matched_at')->nullable()->after('reconciliation_status');

            $table->index('bank_transaction_id');
            $table->index(['bank_code', 'reconciliation_status']);
        });

        // Add bank settings to payment_configurations table
        Schema::table('payment_configurations', function (Blueprint $table) {
            // Equity Bank
            $table->boolean('equity_bank_enabled')->default(false)->after('paystack_subaccount_code');
            $table->string('equity_bank_account_number')->nullable()->after('equity_bank_enabled');
            $table->string('equity_bank_account_name')->nullable()->after('equity_bank_account_number');

            // KCB Bank
            $table->boolean('kcb_bank_enabled')->default(false)->after('equity_bank_account_name');
            $table->string('kcb_bank_account_number')->nullable()->after('kcb_bank_enabled');
            $table->string('kcb_bank_account_name')->nullable()->after('kcb_bank_account_number');

            // Co-operative Bank
            $table->boolean('coop_bank_enabled')->default(false)->after('kcb_bank_account_name');
            $table->string('coop_bank_virtual_account')->nullable()->after('coop_bank_enabled');
            $table->string('coop_bank_account_name')->nullable()->after('coop_bank_virtual_account');

            // Reconciliation settings
            $table->boolean('auto_reconcile_enabled')->default(true)->after('coop_bank_account_name');
        });

        // Create bank reconciliation queue for unmatched payments
        Schema::create('bank_reconciliation_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->nullable()->constrained('users');
            $table->foreignId('payment_id')->nullable()->constrained();
            $table->string('bank_code', 20);
            $table->string('transaction_reference');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'processing', 'matched', 'unmatched', 'error']);
            $table->foreignId('matched_invoice_id')->nullable()->constrained('invoices');
            $table->text('error_message')->nullable();
            $table->json('raw_payload')->nullable();
            $table->datetime('matched_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->datetime('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['bank_code', 'status']);
            $table->index('transaction_reference');
        });

        // Create webhook audit log
        Schema::create('bank_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('bank_code', 20);
            $table->string('event_type')->nullable();
            $table->json('payload');
            $table->enum('status', ['received', 'processing', 'success', 'error']);
            $table->text('error_details')->nullable();
            $table->string('ip_address', 45);
            $table->foreignId('processed_payment_id')->nullable()->constrained('payments');
            $table->timestamps();

            $table->index(['bank_code', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_webhook_logs');
        Schema::dropIfExists('bank_reconciliation_queue');

        Schema::table('payment_configurations', function (Blueprint $table) {
            $table->dropColumn([
                'equity_bank_enabled',
                'equity_bank_account_number',
                'equity_bank_account_name',
                'kcb_bank_enabled',
                'kcb_bank_account_number',
                'kcb_bank_account_name',
                'coop_bank_enabled',
                'coop_bank_virtual_account',
                'coop_bank_account_name',
                'auto_reconcile_enabled',
            ]);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['bank_transaction_id']);
            $table->dropIndex(['bank_code', 'reconciliation_status']);
            $table->dropColumn([
                'bank_code',
                'bank_account_number',
                'bank_transaction_id',
                'bank_transaction_date',
                'bank_reference',
                'reconciliation_status',
                'reconciliation_matched_at',
            ]);
        });
    }
};
