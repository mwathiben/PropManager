<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\Currency;
use App\Models\PaymentConfiguration;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-11 SECRETS-4: rotate per-landlord webhook secret with audit
 * trail. These tests pin the dry-run / confirm split, the column
 * actually getting updated, and the SecurityLog entry capturing
 * before/after hashes.
 */
class WebhookRotateSecretTest extends TestCase
{
    use RefreshDatabase;

    private function makeLandlordWithConfig(string $oldSecret = 'pre-rotation-secret'): User
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        PaymentConfiguration::create([
            'landlord_id' => $landlord->id,
            'water_billing_type' => 'consumption',
            'water_unit_rate' => 150,
            'accepted_payment_methods' => ['bank_transfer'],
            'default_currency' => Currency::KES,
            'coop_webhook_secret' => $oldSecret,
            'paystack_enabled' => false,
            'intasend_enabled' => false,
        ]);

        return $landlord;
    }

    public function test_dry_run_does_not_mutate_or_log(): void
    {
        $landlord = $this->makeLandlordWithConfig('old');

        $this->artisan('webhook:rotate-secret', [
            '--bank' => 'coop',
            '--landlord' => $landlord->id,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('DRY RUN');

        $config = PaymentConfiguration::withoutGlobalScopes()
            ->where('landlord_id', $landlord->id)
            ->first();
        $this->assertSame('old', $config->coop_webhook_secret);
        $this->assertSame(0, SecurityLog::where('event_type', 'webhook_secret_rotated')->count());
    }

    public function test_confirm_rotates_secret_and_writes_audit_log(): void
    {
        $landlord = $this->makeLandlordWithConfig('old-secret');

        $this->artisan('webhook:rotate-secret', [
            '--bank' => 'coop',
            '--landlord' => $landlord->id,
            '--confirm' => true,
            '--reason' => 'unit test',
        ])->assertExitCode(0);

        $config = PaymentConfiguration::withoutGlobalScopes()
            ->where('landlord_id', $landlord->id)
            ->first();
        $this->assertNotSame('old-secret', $config->coop_webhook_secret);
        $this->assertNotEmpty($config->coop_webhook_secret);

        $log = SecurityLog::where('event_type', 'webhook_secret_rotated')->first();
        $this->assertNotNull($log);
        $this->assertSame((int) $landlord->id, (int) $log->landlord_id);
        $this->assertSame('coop', $log->metadata['bank']);
        $this->assertSame(hash('sha256', 'old-secret'), $log->metadata['old_secret_hash']);
        $this->assertSame('unit test', $log->metadata['reason']);
        $this->assertSame(SecurityLog::SEVERITY_WARNING, $log->severity);
    }

    public function test_other_bank_columns_are_not_touched(): void
    {
        $landlord = $this->makeLandlordWithConfig('coop-secret');
        $config = PaymentConfiguration::withoutGlobalScopes()
            ->where('landlord_id', $landlord->id)
            ->first();
        $config->equity_webhook_secret = 'equity-untouched';
        $config->save();

        $this->artisan('webhook:rotate-secret', [
            '--bank' => 'coop',
            '--landlord' => $landlord->id,
            '--confirm' => true,
        ])->assertExitCode(0);

        $config->refresh();
        $this->assertSame('equity-untouched', $config->equity_webhook_secret);
    }

    public function test_unsupported_bank_is_rejected(): void
    {
        $landlord = $this->makeLandlordWithConfig();

        $this->artisan('webhook:rotate-secret', [
            '--bank' => 'absa',
            '--landlord' => $landlord->id,
            '--confirm' => true,
        ])->assertExitCode(2);
    }

    public function test_missing_landlord_is_rejected(): void
    {
        $this->artisan('webhook:rotate-secret', [
            '--bank' => 'coop',
            '--landlord' => 999999,
            '--confirm' => true,
        ])->assertExitCode(1);
    }

    public function test_non_landlord_user_is_rejected(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);

        $this->artisan('webhook:rotate-secret', [
            '--bank' => 'coop',
            '--landlord' => $tenant->id,
            '--confirm' => true,
        ])->assertExitCode(1);
    }

    public function test_generated_secret_is_url_safe_ascii(): void
    {
        $landlord = $this->makeLandlordWithConfig();

        $this->artisan('webhook:rotate-secret', [
            '--bank' => 'coop',
            '--landlord' => $landlord->id,
            '--confirm' => true,
        ])->assertExitCode(0);

        $config = PaymentConfiguration::withoutGlobalScopes()
            ->where('landlord_id', $landlord->id)
            ->first();
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $config->coop_webhook_secret);
        $this->assertGreaterThanOrEqual(40, strlen($config->coop_webhook_secret));
    }
}
