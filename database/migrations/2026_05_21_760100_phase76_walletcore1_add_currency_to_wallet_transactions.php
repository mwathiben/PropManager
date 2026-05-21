<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-76 WALLET-DEEP MULTI-CCY-1: stamp each wallet movement with its
 * currency so a tenant can hold credit in more than one currency. Existing
 * rows backfill to each landlord's configured default currency (KES otherwise).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('currency', 3)->default('KES')->after('amount');
        });

        DB::table('payment_configurations')
            ->whereNotNull('default_currency')
            ->where('default_currency', '!=', 'KES')
            ->select('landlord_id', 'default_currency')
            ->get()
            ->each(function ($config) {
                DB::table('wallet_transactions')
                    ->where('landlord_id', $config->landlord_id)
                    ->update(['currency' => $config->default_currency]);
            });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
