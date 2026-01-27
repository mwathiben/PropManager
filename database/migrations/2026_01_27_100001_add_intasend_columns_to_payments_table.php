<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('intasend_transaction_id', 50)
                ->nullable()
                ->after('mpesa_checkout_request_id');
            $table->string('intasend_reference', 100)
                ->nullable()
                ->after('intasend_transaction_id');

            $table->index('intasend_transaction_id', 'payments_intasend_txn_idx');
            $table->index('intasend_reference', 'payments_intasend_ref_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_intasend_txn_idx');
            $table->dropIndex('payments_intasend_ref_idx');
            $table->dropColumn(['intasend_transaction_id', 'intasend_reference']);
        });
    }
};
