<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Models\OperationalIncident;
use App\Models\PaymentConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-42 PAYOUT-AUDIT-1/2: payouts:stripe-balance-audit cron +
 * payout.failed webhook handler + sev3 OperationalIncident.
 */
class Phase42PayoutAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_payouts_balance_audit_noops_when_stripe_unconfigured(): void
    {
        $this->artisan('payouts:stripe-balance-audit')
            ->assertExitCode(0)
            ->expectsOutputToContain('not configured');
    }

    public function test_payout_balance_audit_scheduled_twice_daily_at_03_15_and_15_15_africa_nairobi(): void
    {
        $entry = collect(\Illuminate\Support\Facades\Schedule::events())
            ->first(fn ($e) => str_contains((string) $e->command, 'payouts:stripe-balance-audit'));

        $this->assertNotNull($entry, 'payouts:stripe-balance-audit must be scheduled');
        // Laravel's twiceDailyAt(3, 15, 15) compiles to '15 3,15 * * *'
        $this->assertSame('15 3,15 * * *', $entry->expression);
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    public function test_payout_failed_webhook_opens_sev3_operational_incident(): void
    {
        $secret = 'whsec_test_payout_failed_'.uniqid();
        config(['services.stripe.webhook_secret' => $secret]);

        $landlord = User::factory()->create(['role' => 'landlord']);
        $accountId = 'acct_test_'.uniqid();
        PaymentConfiguration::factory()->forLandlord($landlord)->create([
            'stripe_connect_account_id' => $accountId,
        ]);

        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'payout.failed',
            'data' => ['object' => [
                'id' => 'po_test_'.uniqid(),
                'destination' => $accountId,
                'failure_message' => 'account_closed',
                'amount' => 5000,
                'currency' => 'usd',
            ]],
        ];

        $payoutId = (string) ($payload['data']['object']['id']);
        $response = $this->call('POST', '/webhooks/v2/stripe', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => $this->sign($payload, $secret)],
            json_encode($payload));

        $response->assertStatus(200);

        // Scope to this payout — count comparisons are fragile under
        // parallel test runs where other tests may also write incidents.
        $incident = OperationalIncident::query()
            ->where('title', 'like', "%{$payoutId}%")
            ->first();
        $this->assertNotNull($incident);
        $this->assertSame(OperationalIncident::SEV3, $incident->severity);
        $this->assertStringContainsString('account_closed', $incident->summary);
        $this->assertStringContainsString((string) $landlord->id, $incident->summary);
    }

    public function test_payout_failed_webhook_with_unknown_destination_still_logs_incident(): void
    {
        $secret = 'whsec_test_payout_unknown_'.uniqid();
        config(['services.stripe.webhook_secret' => $secret]);

        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'payout.failed',
            'data' => ['object' => [
                'id' => 'po_test_'.uniqid(),
                'destination' => 'acct_unknown_'.uniqid(),
                'failure_message' => 'insufficient_funds',
            ]],
        ];

        $response = $this->call('POST', '/webhooks/v2/stripe', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => $this->sign($payload, $secret)],
            json_encode($payload));

        $response->assertStatus(200);
        // Incident still opens even when the landlord can't be resolved.
        // Scope to "this payout's" incident — count comparisons are fragile
        // under parallel runs where other tests may also write incidents.
        $payoutId = (string) ($payload['data']['object']['id']);
        $this->assertNotNull(
            OperationalIncident::query()
                ->where('title', 'like', "%{$payoutId}%")
                ->first(),
        );
    }

    private function sign(array $payload, string $secret): string
    {
        $timestamp = time();
        $signedPayload = $timestamp.'.'.json_encode($payload);

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $signedPayload, $secret);
    }
}
