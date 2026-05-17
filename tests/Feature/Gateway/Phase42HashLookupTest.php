<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Models\PaymentConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-42 follow-up: stripe_connect_account_id_hash searchable
 * column + PaymentConfiguration::findByConnectAccountId helper.
 * Replaces the O(n) decrypted-scan workaround Phase 42 Phase 1f
 * shipped at handlePayoutFailed.
 */
class Phase42HashLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_configurations_has_hash_column_with_index(): void
    {
        $cols = Schema::getColumnListing('payment_configurations');
        $this->assertContains('stripe_connect_account_id_hash', $cols);
    }

    public function test_setting_account_id_populates_hash(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $accountId = 'acct_test_'.uniqid();

        $config = PaymentConfiguration::factory()->forLandlord($landlord)->create([
            'stripe_connect_account_id' => $accountId,
        ]);

        $config->refresh();
        $this->assertSame(hash('sha256', $accountId), $config->stripe_connect_account_id_hash);
        $this->assertSame($accountId, (string) $config->stripe_connect_account_id);
    }

    public function test_clearing_account_id_clears_hash(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $config = PaymentConfiguration::factory()->forLandlord($landlord)->create([
            'stripe_connect_account_id' => 'acct_test_'.uniqid(),
        ]);
        $this->assertNotNull($config->stripe_connect_account_id_hash);

        $config->stripe_connect_account_id = null;
        $config->save();
        $config->refresh();

        $this->assertNull($config->stripe_connect_account_id);
        $this->assertNull($config->stripe_connect_account_id_hash);
    }

    public function test_find_by_connect_account_id_returns_match(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $accountId = 'acct_lookup_'.uniqid();
        PaymentConfiguration::factory()->forLandlord($landlord)->create([
            'stripe_connect_account_id' => $accountId,
        ]);

        $found = PaymentConfiguration::findByConnectAccountId($accountId);
        $this->assertNotNull($found);
        $this->assertSame((int) $landlord->id, (int) $found->landlord_id);
    }

    public function test_find_by_connect_account_id_returns_null_for_unknown(): void
    {
        $this->assertNull(PaymentConfiguration::findByConnectAccountId('acct_not_in_db_'.uniqid()));
    }

    public function test_find_by_connect_account_id_rejects_empty_string(): void
    {
        $this->assertNull(PaymentConfiguration::findByConnectAccountId(''));
    }

    public function test_payout_failed_webhook_resolves_landlord_via_hash(): void
    {
        $secret = 'whsec_test_hash_'.uniqid();
        config(['services.stripe.webhook_secret' => $secret]);

        $landlord = User::factory()->create(['role' => 'landlord']);
        $accountId = 'acct_hash_lookup_'.uniqid();
        PaymentConfiguration::factory()->forLandlord($landlord)->create([
            'stripe_connect_account_id' => $accountId,
        ]);

        $payoutId = 'po_hash_'.uniqid();
        $payload = [
            'id' => 'evt_'.uniqid(),
            'type' => 'payout.failed',
            'data' => ['object' => [
                'id' => $payoutId,
                'destination' => $accountId,
                'failure_message' => 'account_closed',
            ]],
        ];

        $response = $this->call('POST', '/webhooks/v2/stripe', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_Stripe-Signature' => $this->sign($payload, $secret)],
            json_encode($payload));

        $response->assertStatus(200);

        $incident = \App\Models\OperationalIncident::query()
            ->where('title', 'like', "%{$payoutId}%")
            ->first();
        $this->assertNotNull($incident);
        // The landlord should now be resolved via the hash lookup, not 0.
        $this->assertStringContainsString("landlord {$landlord->id}", $incident->summary);
    }

    private function sign(array $payload, string $secret): string
    {
        $timestamp = time();
        $signedPayload = $timestamp.'.'.json_encode($payload);

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $signedPayload, $secret);
    }
}
