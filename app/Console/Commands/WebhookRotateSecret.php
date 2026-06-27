<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PaymentConfiguration;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Phase-11 SECRETS-4: rotate a single landlord's bank-webhook secret.
 *
 * CRYPTO-11 introduced encrypted per-landlord {coop,equity,kcb}_
 * webhook_secret columns on payment_configurations. Rotating one of
 * them previously required tinker + raw DB edits, with no audit trail.
 *
 * This command:
 *   1. Validates bank in {coop, equity, kcb} and the landlord exists.
 *   2. Records the pre-rotation secret hash (for forensic reference).
 *   3. Generates a new 32-byte secret (base64 url-safe, ASCII-only).
 *   4. Persists the new value via the model's encrypted cast.
 *   5. Writes a SecurityLog row with the rotation actor + before/after
 *      hashes so an incident response can answer "who rotated this,
 *      when, and was the old one already in use?"
 *
 * Operators MUST relay the new plaintext secret to the bank within
 * the validity window (default 24h on the bank side) — the bank will
 * keep signing callbacks with the OLD secret until they re-key. The
 * controller's validateWebhook already handles fallback gracefully.
 *
 * Usage:
 *   php artisan webhook:rotate-secret --bank=coop --landlord=42 --confirm
 *   php artisan webhook:rotate-secret --bank=coop --landlord=42 \
 *     --confirm --reason="Suspected leak in tenant TX-12345"
 */
class WebhookRotateSecret extends Command
{
    protected $signature = 'webhook:rotate-secret
        {--bank= : One of: coop, equity, kcb}
        {--landlord= : Landlord user id whose secret to rotate}
        {--confirm : Required to actually perform the rotation}
        {--reason= : Free-text reason recorded in SecurityLog}';

    protected $description = 'Rotate a landlord\'s bank webhook secret (CRYPTO-11) with audit trail.';

    private const SUPPORTED_BANKS = ['coop', 'equity', 'kcb'];

    public function handle(): int
    {
        $bank = (string) $this->option('bank');
        $landlordId = (int) $this->option('landlord');
        $reason = (string) ($this->option('reason') ?? 'unspecified');

        $validationResult = $this->validateInputs($bank, $landlordId);
        if ($validationResult !== self::SUCCESS) {
            return $validationResult;
        }

        $landlord = $this->resolveLandlord($landlordId);
        if ($landlord === null) {
            return self::FAILURE;
        }

        $config = $this->resolveConfig($landlordId);
        if ($config === null) {
            return self::FAILURE;
        }

        $column = "{$bank}_webhook_secret";
        $oldSecret = (string) ($config->{$column} ?? '');
        $oldHash = $oldSecret === '' ? 'none' : hash('sha256', $oldSecret);
        $newSecret = $this->generateSecret();
        $newHash = hash('sha256', $newSecret);

        $context = [
            'bank' => $bank,
            'landlord_id' => $landlordId,
            'landlord_email' => $landlord->email,
            'old_hash' => $oldHash,
            'new_hash' => $newHash,
            'reason' => $reason,
        ];

        if (! $this->option('confirm')) {
            $this->printDryRun($context);

            return self::SUCCESS;
        }

        $config->{$column} = $newSecret;
        $config->save();

        $this->writeAuditLog($context);
        $this->printSuccess($oldHash, $newHash, $newSecret);

        return self::SUCCESS;
    }

    private function validateInputs(string $bank, int $landlordId): int
    {
        if (! in_array($bank, self::SUPPORTED_BANKS, true)) {
            $this->error('--bank must be one of: '.implode(', ', self::SUPPORTED_BANKS));

            return self::INVALID;
        }

        if ($landlordId <= 0) {
            $this->error('--landlord is required (positive integer user id)');

            return self::INVALID;
        }

        return self::SUCCESS;
    }

    private function resolveLandlord(int $landlordId): ?User
    {
        $landlord = User::find($landlordId);
        if (! $landlord || ! $landlord->isScopeOwner()) {
            $this->error("Landlord id={$landlordId} not found, or user is not a landlord.");

            return null;
        }

        return $landlord;
    }

    private function resolveConfig(int $landlordId): ?PaymentConfiguration
    {
        $config = PaymentConfiguration::withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->first();

        if (! $config) {
            $this->error("No PaymentConfiguration found for landlord id={$landlordId}.");

            return null;
        }

        return $config;
    }

    /** @param array{bank:string,landlord_id:int,landlord_email:string,old_hash:string,new_hash:string,reason:string} $ctx */
    private function printDryRun(array $ctx): void
    {
        $this->warn('DRY RUN — pass --confirm to apply.');
        $this->line("Bank:          {$ctx['bank']}");
        $this->line("Landlord id:   {$ctx['landlord_id']} ({$ctx['landlord_email']})");
        $this->line("Old hash:      {$ctx['old_hash']}");
        $this->line("New hash:      {$ctx['new_hash']}");
        $this->line('Reason:        '.$ctx['reason']);
    }

    /** @param array{bank:string,landlord_id:int,landlord_email:string,old_hash:string,new_hash:string,reason:string} $ctx */
    private function writeAuditLog(array $ctx): void
    {
        $actor = $this->resolveActor();
        SecurityLog::create([
            'user_id' => $actor?->id,
            'landlord_id' => $ctx['landlord_id'],
            'event_type' => 'webhook_secret_rotated',
            'severity' => SecurityLog::SEVERITY_WARNING,
            'description' => "Rotated {$ctx['bank']}_webhook_secret for landlord {$ctx['landlord_id']}",
            'metadata' => [
                'bank' => $ctx['bank'],
                'landlord_id' => $ctx['landlord_id'],
                'old_secret_hash' => $ctx['old_hash'],
                'new_secret_hash' => $ctx['new_hash'],
                'rotated_by' => $actor?->email ?? 'cli',
                'reason' => $ctx['reason'],
            ],
            'ip_address' => null,
            'user_agent' => 'artisan webhook:rotate-secret',
        ]);
    }

    private function printSuccess(string $oldHash, string $newHash, string $newSecret): void
    {
        $this->info('Rotation complete.');
        $this->line("Old hash: {$oldHash}");
        $this->line("New hash: {$newHash}");
        $this->newLine();
        $this->warn('NEW SECRET (relay to the bank within 24h, then it will not be shown again):');
        $this->line($newSecret);
    }

    private function generateSecret(): string
    {
        // 32 bytes -> 43-char base64-url (no '=' padding); ASCII-safe
        // for HTTP header transport on the bank side.
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function resolveActor(): ?User
    {
        // Artisan runs without auth; allow an optional ROTATED_BY env
        // var so a deploy-time script can stamp itself for forensics.
        $actorEmail = env('ROTATED_BY');
        if (! $actorEmail) {
            return null;
        }

        return User::where('email', $actorEmail)->first();
    }
}
