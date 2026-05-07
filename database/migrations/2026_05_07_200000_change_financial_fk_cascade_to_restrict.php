<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->foreignId('payment_id')->change()->constrained()->restrictOnDelete();
        });

        Schema::table('platform_fees', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->foreignId('payment_id')->change()->constrained()->restrictOnDelete();
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->foreignId('payment_id')->change()->constrained()->restrictOnDelete();
        });

        Schema::table('late_fees', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->foreignId('invoice_id')->change()->constrained()->restrictOnDelete();
        });

        Schema::table('deposit_transactions', function (Blueprint $table) {
            $table->dropForeign(['lease_id']);
            $table->foreignId('lease_id')->change()->constrained()->restrictOnDelete();
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropForeign(['lease_id']);
            $table->foreignId('lease_id')->change()->constrained()->restrictOnDelete();
        });

        Schema::table('rent_histories', function (Blueprint $table) {
            $table->dropForeign(['lease_id']);
            $table->foreignId('lease_id')->change()->constrained()->restrictOnDelete();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['landlord_id']);
            $table->dropForeign(['recipient_id']);
            $table->foreignId('landlord_id')->change()->constrained('users')->restrictOnDelete();
            $table->foreignId('recipient_id')->nullable()->change()->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->foreignId('payment_id')->change()->constrained()->cascadeOnDelete();
        });

        Schema::table('platform_fees', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->foreignId('payment_id')->change()->constrained()->cascadeOnDelete();
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->foreignId('payment_id')->change()->constrained()->cascadeOnDelete();
        });

        Schema::table('late_fees', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->foreignId('invoice_id')->change()->constrained()->cascadeOnDelete();
        });

        Schema::table('deposit_transactions', function (Blueprint $table) {
            $table->dropForeign(['lease_id']);
            $table->foreignId('lease_id')->change()->constrained()->cascadeOnDelete();
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropForeign(['lease_id']);
            $table->foreignId('lease_id')->change()->constrained()->cascadeOnDelete();
        });

        Schema::table('rent_histories', function (Blueprint $table) {
            $table->dropForeign(['lease_id']);
            $table->foreignId('lease_id')->change()->constrained()->cascadeOnDelete();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['landlord_id']);
            $table->dropForeign(['recipient_id']);
            $table->foreignId('landlord_id')->change()->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->change()->constrained('users')->cascadeOnDelete();
        });
    }
};
