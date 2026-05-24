<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Mail\InvoiceSent;
use App\Models\Invoice;
use App\Models\Meter;
use App\Models\PaymentConfiguration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaterConnection;
use App\Models\WaterReading;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Services\Water\WaterAccountService;
use App\Services\Water\WaterClientBillingService;
use App\Services\Water\WaterModuleAccess;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-98 WATER-CLIENT-INVOICING-UNIFY: water-client bills are real invoices in the
 * one invoicing system (not the retired water_client_charges table). Tenants still
 * get ONE invoice (rent + water). The two billing guards still hold.
 */
class Phase98WaterClientInvoicingTest extends TestCase
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

    private function meter(): Meter
    {
        return Meter::factory()->create(['landlord_id' => $this->landlord->id, 'unit_id' => $this->unit->id]);
    }

    private function connection(array $extra = []): WaterConnection
    {
        return WaterConnection::factory()->create(array_merge([
            'landlord_id' => $this->landlord->id,
            'connected_at' => $this->period->subYear()->toDateString(),
        ], $extra));
    }

    private function reading(Meter $meter, float $consumption): WaterReading
    {
        return Model::withoutEvents(fn () => WaterReading::factory()->forMeter($meter)->create([
            'version' => 1,
            'reading_date' => $this->period->addDays(10)->toDateString(),
            'previous_reading' => 0,
            'current_reading' => $consumption,
            'consumption' => $consumption,
            'cost' => 0,
            'status' => 'approved',
            'is_invoiced' => false,
        ]));
    }

    private function waterClient(): User
    {
        return Model::withoutEvents(fn () => User::factory()->create([
            'role' => 'water_client', 'landlord_id' => $this->landlord->id, 'email_verified_at' => now(),
        ]));
    }

    // --- BILLING CREATES REAL INVOICES ----------------------------------

    public function test_metered_connection_creates_a_lease_less_invoice_at_the_client_rate(): void
    {
        $meter = $this->meter();
        $connection = $this->connection(['meter_id' => $meter->id, 'client_rate' => 200]);
        $this->reading($meter, 10);

        $result = $this->billing->billConnection($connection, $this->period);

        $this->assertSame('billed', $result['status']);
        $invoice = $result['invoice'];
        $this->assertNull($invoice->lease_id);
        $this->assertSame($connection->id, $invoice->water_connection_id);
        $this->assertEqualsWithDelta(2000.0, (float) $invoice->water_due, 0.01); // 10 * 200
        $this->assertEqualsWithDelta(2000.0, (float) $invoice->total_due, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $invoice->rent_due, 0.01);
    }

    public function test_flat_rate_connection_creates_a_fixed_invoice(): void
    {
        $connection = $this->connection(['meter_id' => null, 'billing_mode' => 'flat_rate', 'client_rate' => 500]);

        $invoice = $this->billing->billConnection($connection, $this->period)['invoice'];

        $this->assertEqualsWithDelta(500.0, (float) $invoice->total_due, 0.01);
        $this->assertSame($connection->id, $invoice->water_connection_id);
    }

    public function test_billing_is_idempotent_per_period(): void
    {
        $meter = $this->meter();
        $connection = $this->connection(['meter_id' => $meter->id, 'client_rate' => 200]);
        $this->reading($meter, 10);

        $this->billing->billConnection($connection, $this->period);
        $second = $this->billing->billConnection($connection, $this->period);

        $this->assertSame('already_billed', $second['status']);
        $this->assertSame(1, Invoice::withoutGlobalScopes()->where('water_connection_id', $connection->id)->count());
    }

    public function test_guard_refuses_no_rate_no_invoice_created(): void
    {
        PaymentConfiguration::where('landlord_id', $this->landlord->id)->update(['water_client_rate' => null]);
        $meter = $this->meter();
        $connection = $this->connection(['meter_id' => $meter->id, 'client_rate' => null]);
        $this->reading($meter, 10);

        $result = $this->billing->billConnection($connection, $this->period);

        $this->assertSame('skipped', $result['status']);
        $this->assertSame('no_rate', $result['reason']);
        $this->assertSame(0, Invoice::withoutGlobalScopes()->where('water_connection_id', $connection->id)->count());
    }

    public function test_guard_refuses_metered_without_meter(): void
    {
        $connection = $this->connection(['meter_id' => null, 'billing_mode' => 'metered', 'client_rate' => 200]);

        $result = $this->billing->billConnection($connection, $this->period);

        $this->assertSame('skipped', $result['status']);
        $this->assertSame('metered_no_meter', $result['reason']);
    }

    // --- READ SURFACES (dashboard + finances) ---------------------------

    public function test_dashboard_charges_come_from_invoices(): void
    {
        $meter = $this->meter();
        $connection = $this->connection(['meter_id' => $meter->id, 'client_rate' => 200]);
        $this->reading($meter, 10);
        $this->billing->billConnection($connection, $this->period);

        $charges = app(WaterAccountService::class)->chargeHistoryForConnection($connection);

        $this->assertCount(1, $charges);
        $this->assertEqualsWithDelta(2000.0, $charges[0]['water_due'], 0.01);
    }

    public function test_water_client_finances_lists_invoices(): void
    {
        $client = $this->waterClient();
        $connection = $this->connection(['user_id' => $client->id, 'meter_id' => null, 'billing_mode' => 'flat_rate', 'client_rate' => 500]);
        $this->billing->billConnection($connection, $this->period);

        $props = $this->actingAs($client->fresh())
            ->get(route('water-client.finances'))
            ->assertOk()
            ->viewData('page');

        $this->assertSame('WaterClient/Finances', $props['component']);
        $this->assertEqualsWithDelta(500.0, (float) $props['props']['totalOutstanding'], 0.01);
    }

    // --- NULL-SAFETY OF THE INVOICE MACHINERY ---------------------------

    public function test_water_client_invoice_pdf_renders_without_a_lease(): void
    {
        $connection = $this->connection(['user_id' => $this->waterClient()->id, 'meter_id' => null, 'billing_mode' => 'flat_rate', 'client_rate' => 500]);
        $invoice = $this->billing->billConnection($connection, $this->period)['invoice'];

        $pdf = app(InvoicePdfService::class)->generatePdf($invoice->fresh());

        $this->assertNotEmpty($pdf->output());
    }

    public function test_invoice_sent_mailable_renders_for_a_water_client(): void
    {
        $connection = $this->connection(['user_id' => $this->waterClient()->id, 'meter_id' => null, 'billing_mode' => 'flat_rate', 'client_rate' => 500]);
        $invoice = $this->billing->billConnection($connection, $this->period)['invoice'];

        $html = (new InvoiceSent($invoice->fresh()))->render();

        $this->assertStringContainsString($invoice->invoice_number, $html);
    }

    public function test_command_creates_invoices_and_emails_the_client(): void
    {
        Mail::fake();
        $client = $this->waterClient();
        $meter = $this->meter();
        $connection = $this->connection(['user_id' => $client->id, 'meter_id' => $meter->id, 'client_rate' => 200]);
        $this->reading($meter, 10);

        $this->artisan('water:bill-clients', ['--month' => $this->period->format('Y-m-d')])->assertExitCode(0);

        $this->assertDatabaseHas('invoices', ['water_connection_id' => $connection->id, 'lease_id' => null]);
        Mail::assertQueued(InvoiceSent::class, fn (InvoiceSent $m) => $m->hasTo($client->email));
    }

    // --- LANDLORD SETTLES + VIEWS A WATER-CLIENT INVOICE IN THE FINANCES HUB ---

    public function test_landlord_records_overpayment_on_water_client_invoice_without_error(): void
    {
        $connection = $this->connection(['user_id' => $this->waterClient()->id, 'meter_id' => null, 'billing_mode' => 'flat_rate', 'client_rate' => 500]);
        $invoice = $this->billing->billConnection($connection, $this->period)['invoice'];

        // Overpay (600 on a 500 bill) — a water-client invoice has no lease wallet, so
        // this must not NPE on $invoice->lease->creditToWallet(). The invoice is settled
        // to total_due and the 100 surplus is surfaced (not absorbed into a wallet).
        $this->actingAs($this->landlord->fresh())
            ->post(route('invoices.recordPayment', $invoice), [
                'amount' => 600,
                'payment_method' => 'cash',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $invoice->refresh();
        $this->assertSame(\App\Enums\InvoiceStatus::Paid, $invoice->status);
        $this->assertEqualsWithDelta(500.0, (float) $invoice->amount_paid, 0.01);
        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'lease_id' => null, 'amount' => 600]);
    }

    public function test_landlord_downloads_a_water_client_invoice_pdf(): void
    {
        $connection = $this->connection(['user_id' => $this->waterClient()->id, 'meter_id' => null, 'billing_mode' => 'flat_rate', 'client_rate' => 500]);
        $invoice = $this->billing->billConnection($connection, $this->period)['invoice'];

        $response = $this->actingAs($this->landlord->fresh())
            ->get(route('invoices.download', $invoice));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_finances_invoice_list_shows_the_water_client_recipient(): void
    {
        $client = Model::withoutEvents(fn () => User::factory()->create([
            'role' => 'water_client', 'landlord_id' => $this->landlord->id, 'name' => 'Neighbour Njoroge', 'email_verified_at' => now(),
        ]));
        $connection = $this->connection(['user_id' => $client->id, 'identifier' => 'WL-007', 'meter_id' => null, 'billing_mode' => 'flat_rate', 'client_rate' => 500]);
        $this->billing->billConnection($connection, $this->period);

        $page = $this->actingAs($this->landlord->fresh())
            ->get(route('finances.invoices'))
            ->assertOk()
            ->viewData('page');

        $rows = collect($page['props']['invoices']['data']);
        $waterRow = $rows->firstWhere('water_connection_id', $connection->id);
        $this->assertNotNull($waterRow, 'water-client invoice should appear in the finances list');
        $this->assertSame('Neighbour Njoroge', $waterRow['recipient']['name']);
        $this->assertSame('WL-007', $waterRow['recipient']['context']);
    }

    public function test_finances_invoice_detail_shows_the_water_client(): void
    {
        $client = Model::withoutEvents(fn () => User::factory()->create([
            'role' => 'water_client', 'landlord_id' => $this->landlord->id, 'name' => 'Neighbour Njoroge', 'email_verified_at' => now(),
        ]));
        $connection = $this->connection(['user_id' => $client->id, 'identifier' => 'WL-007', 'meter_id' => null, 'billing_mode' => 'flat_rate', 'client_rate' => 500]);
        $invoice = $this->billing->billConnection($connection, $this->period)['invoice'];

        $this->actingAs($this->landlord->fresh())
            ->getJson(route('finances.invoices.detail', $invoice))
            ->assertOk()
            ->assertJsonPath('invoice.tenant.name', 'Neighbour Njoroge')
            ->assertJsonPath('invoice.unit.unit_number', 'WL-007');
    }

    // --- TENANT INVOICE STILL COMBINES RENT + WATER (the user's core ask) ---

    public function test_tenant_gets_one_invoice_with_rent_and_water(): void
    {
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $this->unit);
        $lease->update(['rent_amount' => 25000]);
        $meter = $this->meter();
        Model::withoutEvents(fn () => WaterReading::factory()->forMeter($meter)->create([
            'version' => 1, 'reading_date' => $this->period->addDays(5)->toDateString(),
            'previous_reading' => 0, 'current_reading' => 12, 'consumption' => 12, 'cost' => 0,
            'status' => 'approved', 'is_invoiced' => false,
        ]));

        $invoice = app(InvoiceService::class)->generateInvoiceForLease($lease->fresh(), Carbon::parse($this->period->toDateString()));

        $this->assertSame($lease->id, $invoice->lease_id);
        $this->assertNull($invoice->water_connection_id);
        $this->assertEqualsWithDelta(25000.0, (float) $invoice->rent_due, 0.01);
        $this->assertGreaterThan(0, (float) $invoice->water_due); // rent + water on ONE invoice
    }

    // --- SURFACE ---------------------------------------------------------

    public function test_schema_and_retirement(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('invoices', 'water_connection_id'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('water_client_charges'));
    }
}
