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

        if (! in_array($bank, self::SUPPORTED_BANKS, true)) {
            $this->error('--bank must be one of: '.implode(', ', self::SUPPORTED_BANKS));

            return self::INVALID;
        }

        if ($landlordId <= 0) {
            $this->error('--landlord is required (positive integer user id)');

            return self::INVALID;
        }

        $landlord = User::find($landlordId);
        if (! $landlord || ! $landlord->isScopeOwner()) {
            $this->error("Landlord id={$landlordId} not found, or user is not a landlord.");

            return self::FAILURE;
        }

        $config = PaymentConfiguration::withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->first();
        if (! $config) {
            $this->error("No PaymentConfiguration found for landlord id={$landlordId}.");

            return self::FAILURE;
        }

        $column = "{$bank}_webhook_secret";
        $oldSecret = (string) ($config->{$column} ?? '');
        $oldHash = $oldSecret === '' ? 'none' : hash('sha256', $oldSecret);

        $newSecret = $this->generateSecret();
        $newHash = hash('sha256', $newSecret);

        if (! $this->option('confirm')) {
            $this->warn('DRY RUN — pass --confirm to apply.');
            $this->line("Bank:          {$bank}");
            $this->line("Landlord id:   {$landlordId} ({$landlord->email})");
            $this->line("Old hash:      {$oldHash}");
            $this->line("New hash:      {$newHash}");
            $this->line('Reason:        '.$reason);

            return self::SUCCESS;
        }

        $config->{$column} = $newSecret;
        $config->save();

        $actor = $this->resolveActor();
        SecurityLog::create([
            'user_id' => $actor?->id,
            'landlord_id' => $landlordId,
            'event_type' => 'webhook_secret_rotated',
            'severity' => SecurityLog::SEVERITY_WARNING,
            'description' => "Rotated {$bank}_webhook_secret for landlord {$landlordId}",
            'metadata' => [
                'bank' => $bank,
                'landlord_id' => $landlordId,
                'old_secret_hash' => $oldHash,
                'new_secret_hash' => $newHash,
                'rotated_by' => $actor?->email ?? 'cli',
                'reason' => $reason,
            ],
            'ip_address' => null,
            'user_agent' => 'artisan webhook:rotate-secret',
        ]);

        $this->info('Rotation complete.');
        $this->line("Old hash: {$oldHash}");
        $this->line("New hash: {$newHash}");
        $this->newLine();
        $this->warn('NEW SECRET (relay to the bank within 24h, then it will not be shown again):');
        $this->line($newSecret);

        return self::SUCCESS;
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
