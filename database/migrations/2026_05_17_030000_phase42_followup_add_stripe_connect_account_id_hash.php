<?php

declare(strict_types=1);

use App\Models\PaymentConfiguration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-42 follow-up: searchable hash column for
 * payment_configurations.stripe_connect_account_id. The base
 * column is encrypted (Phase-41 GATEWAY-CONNECT-2) so direct
 * WHERE-clause reverse lookups never match. SHA256 hex (64
 * chars) of the plaintext account id gives O(1) lookup without
 * leaking the value at rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_configurations', function (Blueprint $table): void {
            $table->string('stripe_connect_account_id_hash', 64)
                ->nullable()
                ->after('stripe_connect_account_id');
            $table->index('stripe_connect_account_id_hash', 'pc_stripe_connect_acct_hash');
        });

        // Backfill existing rows: decrypt via the model so the cast
        // unwraps, then write the SHA256 hex back.
        PaymentConfiguration::query()
            ->whereNotNull('stripe_connect_account_id')
            ->get(['id', 'stripe_connect_account_id'])
            ->each(function (PaymentConfiguration $config): void {
                $plain = (string) $config->stripe_connect_account_id;
                if ($plain === '') {
                    return;
                }
                $config->stripe_connect_account_id_hash = hash('sha256', $plain);
                $config->saveQuietly();
            });
    }

    public function down(): void
    {
        Schema::table('payment_configurations', function (Blueprint $table): void {
            $table->dropIndex('pc_stripe_connect_acct_hash');
            $table->dropColumn('stripe_connect_account_id_hash');
        });
    }
};
