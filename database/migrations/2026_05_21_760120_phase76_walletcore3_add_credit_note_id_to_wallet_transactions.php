<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-76 WALLET-DEEP CREDIT-WALLET-2: link a wallet credit back to the credit
 * note that funded it, so the statement + audit can trace wallet credit to its
 * originating credit note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('credit_note_id')->nullable()->after('payment_id')->constrained('credit_notes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_note_id');
        });
    }
};
