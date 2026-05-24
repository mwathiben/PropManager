<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\Meter;
use App\Models\Notification;
use App\Models\PaymentConfiguration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaterClientCharge;
use App\Models\WaterConnection;
use App\Models\WaterReading;
use App\Services\Water\WaterAccountService;
use App\Services\Water\WaterClientBillingService;
use App\Services\Water\WaterModuleAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-97 WATER-CLIENT-BILLING: the biller (metered/flat, idempotent, the two
 * deferred guards), the dashboard charges, the landlord record-payment, and the
 * client finances surface.
 */
class Phase97WaterClientBillingTest extends TestCase
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

    private function reading(Meter $meter, float $consumption, ?string $date = null): WaterReading
    {
        return Model::withoutEvents(fn () => WaterReading::factory()->forMeter($meter)->create([
            'version' => 1,
            'reading_date' => $date ?? $this->period->addDays(10)->toDateString(),
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
            'role' => 'water_client',
            'landlord_id' => $this->landlord->id,
            'email_verified_at' => now(),
        ]));
    }

    // --- BILLING --------------------------------------------------------

    public function test_metered_connection_bills_consumption_at_the_client_rate(): void
    {
        $meter = $this->meter();
        $connection = $this->connection(['meter_id' => $meter->id, 'billing_mode' => 'metered', 'client_rate' => 200]);
        $this->reading($meter, 10);

        $result = $this->billing->billConnection($connection, $this->period);

        $this->assertSame('billed', $result['status']);
        $this->assertEqualsWithDelta(2000.0, (float) $result['charge']->water_due, 0.01); // 10 * 200
        $this->assertEqualsWithDelta(10.0, (float) $result['charge']->consumption, 0.01);
    }

    public function test_flat_rate_connection_bills_the_fixed_rate(): void
    {
        $connection = $this->connection(['meter_id' => null, 'billing_mode' => 'flat_rate', 'client_rate' => 500]);

        $result = $this->billing->billConnection($connection, $this->period);

        $this->assertSame('billed', $result['status']);
        $this->assertEqualsWithDelta(500.0, (float) $result['charge']->water_due, 0.01);
        $this->assertNull($result['charge']->consumption);
    }

    public function test_connection_falls_back_to_landlord_default_rate(): void
    {
        $meter = $this->meter();
        $connection = $this->connection(['meter_id' => $meter->id, 'billing_mode' => 'metered', 'client_rate' => null]);
        $this->reading($meter, 10);

        $result = $this->billing->billConnection($connection, $this->period);

        // client_rate null -> landlord water_client_rate (200 from setUp): 10 * 200.
        $this->assertEqualsWithDelta(2000.0, (float) $result['charge']->water_due, 0.01);
    }

    public function test_billing_is_idempotent_per_period(): void
    {
        $meter = $this->meter();
        $connection = $this->connection(['meter_id' => $meter->id, 'client_rate' => 200]);
        $this->reading($meter, 10);

        $this->billing->billConnection($connection, $this->period);
        $second = $this->billing->billConnection($connection, $this->period);

        $this->assertSame('already_billed', $second['status']);
        $this->assertSame(1, WaterClientCharge::withoutGlobalScopes()->where('water_connection_id', $connection->id)->count());
    }

    // --- THE TWO DEFERRED GUARDS (refuse, never coerce 0) ---------------

    public function test_guard_refuses_a_connection_with_no_effective_rate(): void
    {
        PaymentConfiguration::where('landlord_id', $this->landlord->id)->update(['water_client_rate' => null]);
        $meter = $this->meter();
        $connection = $this->connection(['meter_id' => $meter->id, 'client_rate' => null]);
        $this->reading($meter, 10);

        $result = $this->billing->billConnection($connection, $this->period);

        $this->assertSame('skipped', $result['status']);
        $this->assertSame('no_rate', $result['reason']);
        $this->assertSame(0, WaterClientCharge::withoutGlobalScopes()->where('water_connection_id', $connection->id)->count());
    }

    public function test_guard_refuses_a_metered_connection_with_no_meter(): void
    {
        $connection = $this->connection(['meter_id' => null, 'billing_mode' => 'metered', 'client_rate' => 200]);

        $result = $this->billing->billConnection($connection, $this->period);

        $this->assertSame('skipped', $result['status']);
        $this->assertSame('metered_no_meter', $result['reason']);
        $this->assertSame(0, WaterClientCharge::withoutGlobalScopes()->where('water_connection_id', $connection->id)->count());
    }

    public function test_zero_rate_is_treated_as_no_rate_not_billed_at_zero(): void
    {
        PaymentConfiguration::where('landlord_id', $this->landlord->id)->update(['water_client_rate' => null]);
        $meter = $this->meter();
        $connection = $this->connection(['meter_id' => $meter->id, 'client_rate' => 0]);
        $this->reading($meter, 10);

        $result = $this->billing->billConnection($connection, $this->period);

        // A 0 rate is "unset", not a real price — refuse, don't bill 0 silently.
        $this->assertSame('skipped', $result['status']);
        $this->assertSame('no_rate', $result['reason']);
    }

    public function test_metered_connection_with_no_readings_is_not_billed(): void
    {
        $meter = $this->meter();
        $connection = $this->connection(['meter_id' => $meter->id, 'client_rate' => 200]);

        $result = $this->billing->billConnection($connection, $this->period);

        $this->assertSame('skipped', $result['status']);
        $this->assertSame('no_consumption', $result['reason']);
    }

    public function test_bill_for_period_isolates_skipped_from_billed(): void
    {
        $meter = $this->meter();
        $this->connection(['meter_id' => $meter->id, 'client_rate' => 200]); // no readings -> skipped (no_consumption)
        $flat = $this->connection(['meter_id' => null, 'billing_mode' => 'flat_rate', 'client_rate' => 300]); // billed

        $result = $this->billing->billForPeriod($this->landlord->id, $this->period);

        $this->assertCount(1, $result['billed']);
        $this->assertSame($flat->id, $result['billed'][0]->water_connection_id);
        $this->assertNotEmpty($result['skipped']);
    }

    // --- RECORD PAYMENT -------------------------------------------------

    public function test_record_payment_applies_across_unpaid_charges_oldest_first(): void
    {
        $connection = $this->connection(['meter_id' => null, 'billing_mode' => 'flat_rate', 'client_rate' => 500]);
        $older = WaterClientCharge::factory()->create([
            'landlord_id' => $this->landlord->id, 'water_connection_id' => $connection->id,
            'billing_period_start' => $this->period->subMonth()->toDateString(), 'water_due' => 500, 'amount_paid' => 0, 'consumption' => null,
        ]);
        $newer = WaterClientCharge::factory()->create([
            'landlord_id' => $this->landlord->id, 'water_connection_id' => $connection->id,
            'billing_period_start' => $this->period->toDateString(), 'water_due' => 500, 'amount_paid' => 0, 'consumption' => null,
        ]);

        $applied = $this->billing->applyPayment($connection, 700);

        $this->assertEqualsWithDelta(700.0, $applied, 0.01);
        $this->assertTrue($older->fresh()->isPaid());
        $this->assertEqualsWithDelta(200.0, (float) $newer->fresh()->amount_paid, 0.01);
        $this->assertSame('partial', $newer->fresh()->status);
    }

    public function test_landlord_can_record_a_payment_via_the_endpoint(): void
    {
        $connection = $this->connection(['meter_id' => null, 'billing_mode' => 'flat_rate', 'client_rate' => 500]);
        $charge = WaterClientCharge::factory()->create([
            'landlord_id' => $this->landlord->id, 'water_connection_id' => $connection->id,
            'billing_period_start' => $this->period->toDateString(), 'water_due' => 500, 'amount_paid' => 0, 'consumption' => null,
        ]);

        $this->actingAs($this->landlord->fresh())
            ->post(route('water.connections.record-payment', $connection->id), ['amount' => 500])
            ->assertRedirect();

        $this->assertTrue($charge->fresh()->isPaid());
    }

    public function test_overpayment_is_surfaced_not_silently_absorbed(): void
    {
        $connection = $this->connection(['meter_id' => null, 'billing_mode' => 'flat_rate', 'client_rate' => 500]);
        $charge = WaterClientCharge::factory()->create([
            'landlord_id' => $this->landlord->id, 'water_connection_id' => $connection->id,
            'billing_period_start' => $this->period->toDateString(), 'water_due' => 500, 'amount_paid' => 0, 'consumption' => null,
        ]);

        $response = $this->actingAs($this->landlord->fresh())
            ->post(route('water.connections.record-payment', $connection->id), ['amount' => 800]);

        $response->assertRedirect()->assertSessionHas('success');
        // Only the balance is applied; the overpayment is reported, not banked.
        $this->assertEqualsWithDelta(500.0, (float) $charge->fresh()->amount_paid, 0.01);
        $this->assertStringContainsString('300.00', (string) session('success'));
    }

    public function test_cannot_record_payment_for_another_landlords_connection(): void
    {
        $other = $this->createLandlordWithFullSetup()['landlord'];
        $foreign = WaterConnection::factory()->create(['landlord_id' => $other->id]);

        $response = $this->actingAs($this->landlord->fresh())
            ->post(route('water.connections.record-payment', $foreign->id), ['amount' => 100]);

        $this->assertContains($response->status(), [403, 404]);
    }

    // --- DASHBOARD + FINANCES SURFACES ----------------------------------

    public function test_dashboard_charges_are_populated_for_a_connection(): void
    {
        $meter = $this->meter();
        $connection = $this->connection(['meter_id' => $meter->id, 'client_rate' => 200]);
        $this->reading($meter, 10);
        $this->billing->billConnection($connection, $this->period);

        $charges = app(WaterAccountService::class)->chargeHistoryForConnection($connection);

        $this->assertCount(1, $charges);
        $this->assertEqualsWithDelta(2000.0, $charges[0]['water_due'], 0.01);
        $this->assertFalse($charges[0]['paid']);
    }

    public function test_water_client_can_view_their_finances(): void
    {
        $client = $this->waterClient();
        $connection = $this->connection(['user_id' => $client->id, 'meter_id' => null, 'billing_mode' => 'flat_rate', 'client_rate' => 500]);
        WaterClientCharge::factory()->create([
            'landlord_id' => $this->landlord->id, 'water_connection_id' => $connection->id,
            'billing_period_start' => $this->period->toDateString(), 'water_due' => 500, 'amount_paid' => 0, 'consumption' => null,
        ]);

        $props = $this->actingAs($client->fresh())
            ->get(route('water-client.finances'))
            ->assertOk()
            ->viewData('page');

        $this->assertSame('WaterClient/Finances', $props['component']);
        $this->assertEqualsWithDelta(500.0, (float) $props['props']['totalOutstanding'], 0.01);
        $this->assertCount(1, $props['props']['lines']);
    }

    // --- COMMAND + NOTIFICATION -----------------------------------------

    public function test_command_bills_active_connections_and_notifies_the_client(): void
    {
        $client = $this->waterClient();
        $meter = $this->meter();
        $connection = $this->connection(['user_id' => $client->id, 'meter_id' => $meter->id, 'client_rate' => 200]);
        $this->reading($meter, 10);

        $this->artisan('water:bill-clients', ['--month' => $this->period->format('Y-m-d')])->assertExitCode(0);

        $this->assertDatabaseHas('water_client_charges', [
            'water_connection_id' => $connection->id,
            'billing_period_start' => $this->period->toDateString(),
        ]);
        $this->assertTrue(
            Notification::where('type', Notification::TYPE_WATER_BILL_DUE)->where('recipient_id', $client->id)->exists(),
            'A water_bill_due notification should be sent to the onboarded client.',
        );
    }
}
