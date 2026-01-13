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
            $table->string('paystack_split_code')->nullable()->after('paystack_reference');
            $table->boolean('is_split_payment')->default(false)->after('paystack_split_code');
            $table->foreignId('payout_account_id')->nullable()->after('landlord_id')
                ->constrained('landlord_payout_accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['payout_account_id']);
            $table->dropColumn(['paystack_split_code', 'is_split_payment', 'payout_account_id']);
        });
    }
};
