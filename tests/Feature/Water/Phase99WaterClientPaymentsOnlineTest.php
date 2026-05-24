<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Enums\InvoiceStatus;
use App\Mail\PaymentReceived;
use App\Models\Invoice;
use App\Models\PaymentConfiguration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaterConnection;
use App\Services\Water\WaterClientBillingService;
use App\Services\Water\WaterModuleAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-99 WATER-CLIENT-PAYMENTS-ONLINE: a water client pays their own (real)
 * invoice online through the supplier's gateway — InvoicePolicy::pay + the gateway
 * request authorizers + the checkout/callback paths are all water-aware.
 */
class Phase99WaterClientPaymentsOnlineTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Unit $unit;

    private WaterClientBillingService $billing;

    private CarbonImmutable $period;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->unit = $setup['units']->first();
        $setup['building']->update(['water_billing_type' => 'consumption', 'water_unit_rate' => 150]);

        $plan = SubscriptionPlan::factory()->create(['water_billing_enabled' => true]);
        Subscription::factory()->create(['user_id' => $this->landlord->id, 'plan_id' => $plan->id, 'status' => 'active']);
        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'water_billing_type' => 'consumption',
            'water_unit_rate' => 150,
            'supplies_water_clients' => true,
            'water_client_rate' => 200,
        ]);
        WaterModuleAccess::forget($this->landlord->id);

        $this->billing = app(WaterClientBillingService::class);
        $this->period = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
    }

    private function waterClient(): User
    {
        return Model::withoutEvents(fn () => User::factory()->create([
            'role' => 'water_client', 'landlord_id' => $this->landlord->id, 'email_verified_at' => now(),
        ]));
    }

    private function billedInvoice(User $client, string $identifier = 'WL-1'): Invoice
    {
        $connection = WaterConnection::factory()->create([
            'landlord_id' => $this->landlord->id,
            'user_id' => $client->id,
            'identifier' => $identifier,
            'meter_id' => null,
            'billing_mode' => 'flat_rate',
            'client_rate' => 500,
            'connected_at' => $this->period->subYear()->toDateString(),
        ]);

        return $this->billing->billConnection($connection, $this->period)['invoice'];
    }

    private function configurePaystack(): void
    {
        // Use a model save so the `encrypted` casts on the key columns apply — a
        // query-builder update() would store plaintext and blow up on decrypt.
        $config = PaymentConfiguration::where('landlord_id', $this->landlord->id)->first();
        $config->paystack_enabled = true;
        $config->paystack_public_key = 'pk_test_water';
        $config->paystack_secret_key = 'sk_test_water';
        $config->save();
        $this->landlord->update(['payment_gateway_preference' => 'paystack']);
    }

    // --- AUTHORIZATION ---------------------------------------------------

    public function test_policy_allows_water_client_to_pay_their_own_invoice(): void
    {
        $client = $this->waterClient();
        $invoice = $this->billedInvoice($client);

        $this->assertTrue(Gate::forUser($client->fresh())->allows('pay', $invoice->fresh()));
    }

    public function test_policy_denies_paying_another_clients_invoice(): void
    {
        $invoice = $this->billedInvoice($this->waterClient());
        $other = $this->waterClient();

        $this->assertFalse(Gate::forUser($other->fresh())->allows('pay', $invoice->fresh()));
    }

    public function test_policy_denies_a_paid_invoice(): void
    {
        $client = $this->waterClient();
        $invoice = $this->billedInvoice($client);
        $invoice->update(['amount_paid' => $invoice->total_due, 'status' => InvoiceStatus::Paid]);

        $this->assertFalse(Gate::forUser($client->fresh())->allows('pay', $invoice->fresh()));
    }

    public function test_checkout_authorizes_the_water_client_for_their_own_invoice(): void
    {
        $client = $this->waterClient();
        $invoice = $this->billedInvoice($client);

        // Gateway intentionally NOT configured → the request passes authorization
        // (no 403) and is stopped only by the unconfigured-gateway guard (400/422).
        $this->landlord->update(['payment_gateway_preference' => 'paystack']);

        $response = $this->actingAs($client->fresh())->postJson(
            route('payments.checkout.initialize', ['invoice' => $invoice->id]),
            ['amount' => $invoice->total_due],
        );

        $this->assertNotSame(403, $response->status());
        $this->assertContains($response->status(), [400, 422]);
    }

    public function test_checkout_forbids_a_water_client_paying_another_clients_invoice(): void
    {
        $invoice = $this->billedInvoice($this->waterClient());
        $other = $this->waterClient();

        $this->actingAs($other->fresh())->postJson(
            route('payments.checkout.initialize', ['invoice' => $invoice->id]),
            ['amount' => $invoice->total_due],
        )->assertForbidden();
    }

    // --- PAY PAGE + FINANCES SURFACE ------------------------------------

    public function test_pay_page_renders_for_the_owning_water_client(): void
    {
        $client = $this->waterClient();
        $invoice = $this->billedInvoice($client);
        $this->configurePaystack();

        $page = $this->actingAs($client->fresh())
            ->get(route('water-client.finances.pay', $invoice->id))
            ->assertOk()
            ->viewData('page');

        $this->assertSame('WaterClient/Pay', $page['component']);
        $this->assertTrue($page['props']['onlinePayEnabled']);
        $this->assertEqualsWithDelta(500.0, (float) $page['props']['invoice']['balance'], 0.01);
    }

    public function test_pay_page_forbidden_for_another_clients_invoice(): void
    {
        $invoice = $this->billedInvoice($this->waterClient());
        $other = $this->waterClient();

        $this->actingAs($other->fresh())
            ->get(route('water-client.finances.pay', $invoice->id))
            ->assertForbidden();
    }

    public function test_finances_surfaces_unpaid_invoices_and_online_flag(): void
    {
        $client = $this->waterClient();
        $this->billedInvoice($client);
        $this->configurePaystack();

        $page = $this->actingAs($client->fresh())
            ->get(route('water-client.finances'))
            ->assertOk()
            ->viewData('page');

        $this->assertTrue($page['props']['onlinePayEnabled']);
        $line = $page['props']['lines'][0];
        $this->assertCount(1, $line['unpaid_invoices']);
        $this->assertEqualsWithDelta(500.0, (float) $line['unpaid_invoices'][0]['balance'], 0.01);
    }

    // --- CALLBACK / NOTIFICATION (water-aware) --------------------------

    public function test_payment_received_event_is_water_aware(): void
    {
        $client = $this->waterClient();
        $invoice = $this->billedInvoice($client);
        $payment = $invoice->payments()->create([
            'landlord_id' => $invoice->landlord_id,
            'lease_id' => null,
            'amount' => 500,
            'payment_method' => 'paystack',
            'payment_date' => now(),
        ]);

        $event = new \App\Events\PaymentReceived($payment->fresh(), $invoice->fresh());

        // Only the landlord channel (a water client has no tenant channel) — no NPE.
        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertSame('private-landlord.'.$invoice->landlord_id, $channels[0]->name);

        $payload = $event->broadcastWith();
        $this->assertSame($client->name, $payload['tenant_name']);
    }

    public function test_gateway_callback_notifies_the_water_client(): void
    {
        Mail::fake();
        \Illuminate\Support\Facades\Event::fake([\App\Events\PaymentReceived::class]);
        $client = $this->waterClient();
        $invoice = $this->billedInvoice($client);
        $payment = $invoice->payments()->create([
            'landlord_id' => $invoice->landlord_id,
            'lease_id' => null,
            'amount' => 500,
            'payment_method' => 'paystack',
            'payment_date' => now(),
        ]);

        $result = \App\Services\Payment\PaymentProcessResult::success($payment->fresh(), $invoice->fresh());
        app(\App\Services\Payment\PaymentCallbackProcessor::class)->sendNotifications($result);

        Mail::assertQueued(PaymentReceived::class, fn (PaymentReceived $m) => $m->hasTo($client->email));
        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\PaymentReceived::class);
    }

    public function test_recording_a_payment_on_a_water_client_invoice_emails_the_client(): void
    {
        Mail::fake();
        $client = $this->waterClient();
        $invoice = $this->billedInvoice($client);

        $this->actingAs($this->landlord->fresh())
            ->post(route('invoices.recordPayment', $invoice), [
                'amount' => 500,
                'payment_method' => 'cash',
            ])
            ->assertRedirect();

        Mail::assertQueued(PaymentReceived::class, fn (PaymentReceived $m) => $m->hasTo($client->email));
    }
}
