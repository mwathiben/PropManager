<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\PaymentConfiguration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaterReading;
use App\Services\Water\WaterAccountService;
use App\Services\Water\WaterModuleAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-93 WATER-TENANT-SELFSERVICE: the unit-centric WaterAccountService +
 * the tenant water dashboard (consumption history, charges, leak self-alert).
 */
class Phase93WaterSelfServiceTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private $units;

    private WaterAccountService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->units = $setup['units'];
        $setup['building']->update(['water_billing_type' => 'consumption', 'water_unit_rate' => 150]);

        $plan = SubscriptionPlan::factory()->create(['water_billing_enabled' => true]);
        Subscription::factory()->create(['user_id' => $this->landlord->id, 'plan_id' => $plan->id, 'status' => 'active']);
        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'water_billing_type' => 'consumption',
            'water_unit_rate' => 150,
        ]);
        WaterModuleAccess::forget($this->landlord->id);

        $this->service = app(WaterAccountService::class);
    }

    private function reading(Unit $unit, float $consumption, ?string $date = null, array $extra = []): WaterReading
    {
        return Model::withoutEvents(fn () => WaterReading::factory()->forUnit($unit)->create(array_merge([
            'version' => 1,
            'reading_date' => $date ?? now()->toDateString(),
            'previous_reading' => 0,
            'current_reading' => $consumption,
            'consumption' => $consumption,
            'cost' => $consumption * 150,
            'status' => 'approved',
            'is_invoiced' => false,
        ], $extra)));
    }

    private function tenantWithLease(int $unitIndex): array
    {
        return Model::withoutEvents(fn () => $this->createTenantWithActiveLease($this->landlord, $this->units->get($unitIndex)));
    }

    // --- SERVICE ---------------------------------------------------------

    public function test_consumption_history_and_summary(): void
    {
        $unit = $this->units->get(0);
        $this->reading($unit, 100, now()->toDateString());
        $this->reading($unit, 200, now()->subMonthNoOverflow()->startOfMonth()->toDateString());

        $out = $this->service->overview($unit->id);

        $this->assertCount(12, $out['history']);
        $this->assertSame(100, end($out['history'])['value']);
        $this->assertSame(150, $out['summary']['avg_monthly']);
        $this->assertSame(100, $out['summary']['latest_consumption']);
        $this->assertSame(300, $out['summary']['ytd_consumption']);
    }

    public function test_charge_history(): void
    {
        ['lease' => $lease] = $this->tenantWithLease(1);
        Model::withoutEvents(fn () => Invoice::create([
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'invoice_number' => 'INV-W93-1',
            'due_date' => now(),
            'billing_period_start' => now()->startOfMonth(),
            'rent_due' => 9000,
            'water_due' => 1000,
            'arrears' => 0,
            'total_due' => 10000,
            'amount_paid' => 10000,
            'status' => InvoiceStatus::Paid,
        ]));

        $charges = $this->service->chargeHistory($lease->id);

        $this->assertCount(1, $charges);
        $this->assertSame(1000.0, $charges[0]['water_due']);
        $this->assertTrue($charges[0]['paid']);
    }

    public function test_latest_anomaly_self_alert(): void
    {
        $unit = $this->units->get(2);
        $this->reading($unit, 50, now()->subDays(40)->toDateString());
        // Latest reading is flagged as a spike.
        $this->reading($unit, 900, now()->toDateString(), ['is_anomalous' => true]);

        $alert = $this->service->latestAnomaly($unit->id);
        $this->assertNotNull($alert);
        $this->assertSame(900, $alert['consumption']);
    }

    public function test_no_alert_when_latest_reading_normal(): void
    {
        $unit = $this->units->get(3);
        $this->reading($unit, 900, now()->subDays(40)->toDateString(), ['is_anomalous' => true]);
        // A later, normal reading supersedes the old anomalous one.
        $this->reading($unit, 60, now()->toDateString());

        $this->assertNull($this->service->latestAnomaly($unit->id));
    }

    public function test_history_excludes_unapproved_readings(): void
    {
        $unit = $this->units->get(4);
        $this->reading($unit, 100, now()->toDateString());
        $this->reading($unit, 500, now()->toDateString(), ['status' => 'pending']);

        $out = $this->service->overview($unit->id);
        $this->assertSame(100, end($out['history'])['value']);
    }

    public function test_readings_scoped_to_tenancy_window(): void
    {
        ['lease' => $lease] = $this->tenantWithLease(7);
        Model::withoutEvents(fn () => $lease->update(['start_date' => now()->subMonthsNoOverflow(2)]));
        $unit = $this->units->get(7);
        // Previous occupant's reading, BEFORE this tenancy started — must be hidden.
        $this->reading($unit, 500, now()->subMonthsNoOverflow(3)->toDateString());
        // This tenant's reading, after the lease started.
        $this->reading($unit, 100, now()->subMonthNoOverflow()->toDateString());

        $since = $lease->fresh()->start_date->toDateString();
        $out = $this->service->overview($unit->id, $lease->id, $since);

        $this->assertSame(100, $out['summary']['ytd_consumption']);
        $this->assertSame(100, $out['summary']['latest_consumption']);
    }

    // --- TENANT SURFACE --------------------------------------------------

    public function test_tenant_sees_water_self_service_payload(): void
    {
        ['tenant' => $tenant, 'lease' => $lease] = $this->tenantWithLease(5);
        $this->reading($this->units->get(5), 120, now()->toDateString());

        $props = $this->actingAs($tenant->fresh())
            ->get(route('tenant.water'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertTrue($props['hasUnit']);
        $this->assertArrayHasKey('history', $props);
        $this->assertArrayHasKey('summary', $props);
        $this->assertArrayHasKey('charges', $props);
        $this->assertArrayHasKey('alert', $props);
        $this->assertCount(12, $props['history']);
    }

    public function test_tenant_with_anomalous_latest_reading_sees_alert(): void
    {
        ['tenant' => $tenant] = $this->tenantWithLease(6);
        $this->reading($this->units->get(6), 800, now()->toDateString(), ['is_anomalous' => true]);

        $props = $this->actingAs($tenant->fresh())
            ->get(route('tenant.water'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertNotNull($props['alert']);
        $this->assertSame(800, $props['alert']['consumption']);
    }

    public function test_non_tenant_cannot_view(): void
    {
        $this->actingAs($this->landlord->fresh())
            ->get(route('tenant.water'))
            ->assertForbidden();
    }

    public function test_tenant_without_lease_gets_empty_state(): void
    {
        $tenant = Model::withoutEvents(fn () => User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]));

        $props = $this->actingAs($tenant->fresh())
            ->get(route('tenant.water'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertFalse($props['hasUnit']);
        $this->assertSame([], $props['history']);
        $this->assertSame([], $props['charges']);
    }
}
