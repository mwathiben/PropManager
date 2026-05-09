<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CRYPTO-4: encrypt landlord_payout_accounts.{account_number,
     * account_name, mobile_number} at rest. Three-step process:
     *   1. Widen the columns to TEXT so the cipher fits.
     *   2. Encrypt every existing row in place using Crypt::encryptString.
     *   3. The model now casts these columns as 'encrypted', so future
     *      reads/writes are transparent.
     *
     * The same `encrypted` cast convention is already used by
     * PaymentConfiguration.bank_account_number — mirror it here.
     */
    public function up(): void
    {
        Schema::table('landlord_payout_accounts', function (Blueprint $table) {
            $table->text('account_number')->nullable()->change();
            $table->text('account_name')->nullable()->change();
            $table->text('mobile_number')->nullable()->change();
        });

        DB::table('landlord_payout_accounts')
            ->select(['id', 'account_number', 'account_name', 'mobile_number'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $update = [];

                    foreach (['account_number', 'account_name', 'mobile_number'] as $field) {
                        $value = $row->{$field};
                        if (! $value) {
                            continue;
                        }
                        if ($this->looksEncrypted($value)) {
                            continue;
                        }
                        $update[$field] = Crypt::encryptString((string) $value);
                    }

                    if ($update) {
                        DB::table('landlord_payout_accounts')
                            ->where('id', $row->id)
                            ->update($update);
                    }
                }
            });
    }

    public function down(): void
    {
        // Decrypt before narrowing — otherwise the cipher truncates.
        DB::table('landlord_payout_accounts')
            ->select(['id', 'account_number', 'account_name', 'mobile_number'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $update = [];

                    foreach (['account_number', 'account_name', 'mobile_number'] as $field) {
                        $value = $row->{$field};
                        if (! $value) {
                            continue;
                        }
                        try {
                            $update[$field] = Crypt::decryptString((string) $value);
                        } catch (\Throwable) {
                            // Already plaintext — leave alone.
                        }
                    }

                    if ($update) {
                        DB::table('landlord_payout_accounts')
                            ->where('id', $row->id)
                            ->update($update);
                    }
                }
            });

        Schema::table('landlord_payout_accounts', function (Blueprint $table) {
            $table->string('account_number')->nullable()->change();
            $table->string('account_name')->nullable()->change();
            $table->string('mobile_number')->nullable()->change();
        });
    }

    /**
     * Best-effort check that a value is already a Laravel ciphertext.
     * Laravel ciphers are JSON-base64 with iv/value/mac/tag fields. A
     * partial match avoids re-encrypting on a re-run of this migration.
     */
    private function looksEncrypted(string $value): bool
    {
        if (! str_starts_with($value, 'eyJ')) {
            return false;
        }
        $decoded = base64_decode($value, true);

        return $decoded !== false && str_contains($decoded, '"iv"') && str_contains($decoded, '"value"');
    }
};
