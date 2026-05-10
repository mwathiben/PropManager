<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LEAK-1 / LEAK-2: encrypt webhook_payload + bank webhook payload at rest.
 *
 * Pre-fix the bank_webhook_logs.payload and intasend_transactions.
 * webhook_payload columns held the FULL provider callback as plain JSON,
 * including phone numbers, account numbers, and reference fields. The
 * Eloquent cast was just 'array', so a DB-backup leak exposed raw PII.
 *
 * The model casts now use 'encrypted:array', which serialises +
 * encrypts on write. The columns must therefore be wide enough for
 * the ciphertext (base64-encoded), so we widen JSON → longText. Then
 * we encrypt any already-stored plaintext rows once. The "already
 * encrypted?" detection lets the migration be re-run safely.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: widen columns so the ciphertext fits.
        Schema::table('bank_webhook_logs', function (Blueprint $table) {
            $table->longText('payload')->change();
        });

        Schema::table('intasend_transactions', function (Blueprint $table) {
            $table->longText('webhook_payload')->nullable()->change();
        });

        // Step 2: encrypt existing rows once. Detect already-encrypted
        // rows (Crypt envelope is JSON containing 'iv' + 'value' + 'mac').
        $this->encryptColumn('bank_webhook_logs', 'payload');
        $this->encryptColumn('intasend_transactions', 'webhook_payload');
    }

    public function down(): void
    {
        // Reversal would require decrypting + re-narrowing; ops should
        // restore from backup if a downgrade is genuinely needed.
        // Keeping down() as a no-op prevents a destructive rollback.
    }

    private function encryptColumn(string $table, string $column): void
    {
        DB::table($table)
            ->select(['id', $column])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $column) {
                foreach ($rows as $row) {
                    $value = $row->{$column};
                    if ($value === null || $value === '') {
                        continue;
                    }

                    if ($this->looksEncrypted($value)) {
                        continue;
                    }

                    // Decode JSON; the cast on read expects an array.
                    $decoded = json_decode($value, true);
                    if (! is_array($decoded)) {
                        // Could be a stringified scalar; wrap so the
                        // 'encrypted:array' read path still works.
                        $decoded = ['raw' => $value];
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update([$column => Crypt::encryptString(json_encode($decoded))]);
                }
            });
    }

    private function looksEncrypted(string $value): bool
    {
        // Laravel's encryptString output is base64 of a JSON envelope:
        // {"iv":"...","value":"...","mac":"...","tag":""}. Cheap probe
        // is base64-decode + json_decode + key check.
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }
        $envelope = json_decode($decoded, true);

        return is_array($envelope)
            && isset($envelope['iv'], $envelope['value'], $envelope['mac']);
    }
};
