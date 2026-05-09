<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * CRYPTO-6: re-encrypt every Laravel-encrypted column with the current
 * APP_KEY after a key rotation. Without this command, rotating APP_KEY
 * permanently breaks every encrypted credential / 2FA secret / setting.
 *
 * Usage:
 *   1. Set the OLD key in .env as `APP_KEY_OLD=base64:...` (the key that
 *      encrypted the rows already in the DB).
 *   2. Set the NEW key as the regular `APP_KEY=base64:...`. Both must be
 *      present at the time you run this command.
 *   3. `php artisan crypt:rotate --dry-run` to preview.
 *   4. `php artisan crypt:rotate --confirm` to perform the migration.
 *   5. After the run completes successfully, remove `APP_KEY_OLD`.
 *
 * The list of encrypted columns is hardcoded; if you add a new
 * `encrypted` cast or `Crypt::encryptString` call site, add it here.
 */
class CryptRotate extends Command
{
    protected $signature = 'crypt:rotate {--dry-run} {--confirm}';

    protected $description = 'Re-encrypt all Laravel-encrypted columns with the current APP_KEY (post-rotation).';

    /**
     * Per-table list of columns that hold Crypt-encrypted values.
     * Order matters only insofar as a partial run + retry is safe — every
     * row is detected as already-encrypted via the iv/value envelope so
     * re-runs become no-ops.
     */
    private array $columns = [
        'users' => ['national_id', 'bank_details'],
        'payment_configurations' => [
            'paystack_secret_key',
            'mpesa_passkey',
            'mpesa_consumer_secret',
            'mpesa_consumer_key',
            'intasend_secret_key',
            'intasend_publishable_key',
            'bank_account_number',
        ],
        'settings' => ['value'],
        'notification_provider_configs' => ['credentials'],
        'landlord_payout_accounts' => ['account_number', 'account_name', 'mobile_number'],
    ];

    public function handle(): int
    {
        $oldKeyRaw = (string) env('APP_KEY_OLD', '');
        if ($oldKeyRaw === '') {
            $this->error('APP_KEY_OLD is not set. Put the previous key in .env and re-run.');

            return self::FAILURE;
        }

        $newKeyRaw = (string) Config::get('app.key');
        if ($newKeyRaw === '' || $oldKeyRaw === $newKeyRaw) {
            $this->error('APP_KEY_OLD must differ from the current APP_KEY.');

            return self::FAILURE;
        }

        $cipher = (string) Config::get('app.cipher', 'AES-256-CBC');
        $oldEncrypter = new Encrypter($this->parseKey($oldKeyRaw), $cipher);

        if ($this->option('dry-run')) {
            $this->info('Dry run — counting rows that need re-encryption.');
        } elseif (! $this->option('confirm')) {
            $this->error('Refusing to mutate without --confirm. Pass --dry-run first to preview.');

            return self::FAILURE;
        }

        $totalChanged = 0;
        $totalSkipped = 0;

        foreach ($this->columns as $table => $fields) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                $this->warn("Skipping {$table}: table does not exist in this environment.");

                continue;
            }

            $existingFields = array_values(array_filter(
                $fields,
                fn (string $f) => DB::getSchemaBuilder()->hasColumn($table, $f),
            ));
            if ($existingFields === []) {
                continue;
            }

            $this->line("Scanning {$table} ({".implode(',', $existingFields).'}) ...');

            DB::table($table)
                ->select(array_merge(['id'], $existingFields))
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table, $existingFields, $oldEncrypter, &$totalChanged, &$totalSkipped) {
                    foreach ($rows as $row) {
                        $update = [];

                        foreach ($existingFields as $field) {
                            $value = $row->{$field};
                            if (! $value) {
                                continue;
                            }

                            $plain = $this->decryptWithOldKey($oldEncrypter, (string) $value);
                            if ($plain === null) {
                                $totalSkipped++;

                                continue;
                            }

                            $update[$field] = Crypt::encryptString($plain);
                        }

                        if ($update !== []) {
                            $totalChanged++;
                            if (! $this->option('dry-run')) {
                                DB::table($table)->where('id', $row->id)->update($update);
                            }
                        }
                    }
                });
        }

        $this->info("Done. Rows changed: {$totalChanged}. Rows skipped (already current or unparseable): {$totalSkipped}.");

        return self::SUCCESS;
    }

    private function decryptWithOldKey(Encrypter $oldEncrypter, string $value): ?string
    {
        try {
            return $oldEncrypter->decryptString($value);
        } catch (\Throwable) {
            // Already encrypted with the new key, or genuinely corrupt /
            // plaintext — either way, skip rather than re-encrypt blindly.
            return null;
        }
    }

    private function parseKey(string $raw): string
    {
        if (str_starts_with($raw, 'base64:')) {
            return base64_decode(substr($raw, 7));
        }

        return $raw;
    }
}
