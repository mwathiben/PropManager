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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('mpesa_transaction_id', 50)->nullable()->after('paystack_reference');
            $table->string('mpesa_checkout_request_id', 100)->nullable()->after('mpesa_transaction_id');

            $table->index('mpesa_transaction_id', 'payments_mpesa_transaction_idx');
            $table->index('mpesa_checkout_request_id', 'payments_mpesa_checkout_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_mpesa_transaction_idx');
            $table->dropIndex('payments_mpesa_checkout_idx');
            $table->dropColumn(['mpesa_transaction_id', 'mpesa_checkout_request_id']);
        });
    }
};
