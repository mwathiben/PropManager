<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Meter;
use App\Models\Notification;
use App\Models\PaymentConfiguration;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WaterPendingCharge;
use App\Services\InvoiceService;
use App\Services\Water\WaterModuleAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-90 WATER-ARREARS-ENFORCEMENT: disconnect/reconnect (unit-meter-only),
 * reconnection fee, arrears, reminder, tenant banner.
 */
class Phase90WaterArrearsEnforcementTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private $units;

    private $building;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->units = $setup['units'];
        $this->building = $setup['building'];
        $this->building->update(['water_billing_type' => 'consumption', 'water_unit_rate' => 150]);

        $plan = SubscriptionPlan::factory()->create(['water_billing_enabled' => true]);
        Subscription::factory()->create(['user_id' => $this->landlord->id, 'plan_id' => $plan->id, 'status' => 'active']);
        PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'water_billing_type' => 'consumption',
            'water_unit_rate' => 150,
        ]);
        WaterModuleAccess::forget($this->landlord->id);
    }

    private function unitMeter(int $i): Meter
    {
        $unit = $this->units->get($i);

        return Meter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $unit->building_id,
            'unit_id' => $unit->id,
            'status' => 'active',
        ]);
    }

    // --- DISCONNECT (caveat) ---------------------------------------------

    public function test_landlord_disconnects_a_unit_meter(): void
    {
        $meter = $this->unitMeter(0);

        $this->actingAs($this->landlord->fresh())
            ->post(route('meters.disconnect', $meter->id), ['reason' => 'Non-payment'])
            ->assertRedirect();

        $this->assertNotNull($meter->fresh()->disconnected_at);
    }

    public function test_a_shared_main_meter_cannot_be_disconnected(): void
    {
        $main = $this->unitMeter(1);
        // Give it a sub-meter -> it's a shared/main meter, not a unit meter.
        Meter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $main->building_id,
            'unit_id' => $main->unit_id,
            'parent_meter_id' => $main->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->landlord->fresh())
            ->post(route('meters.disconnect', $main->id), ['reason' => 'x'])
            ->assertSessionHasErrors('meter');

        $this->assertNull($main->fresh()->disconnected_at);
    }

    public function test_caretaker_cannot_disconnect(): void
    {
        $meter = $this->unitMeter(2);
        $caretaker = Model::withoutEvents(fn () => $this->createCaretakerForLandlord($this->landlord));

        $this->actingAs($caretaker->fresh())
            ->post(route('meters.disconnect', $meter->id))
            ->assertForbidden();
    }

    // --- RECONNECT FEE ---------------------------------------------------

    public function test_reconnect_charges_the_fee_on_the_next_invoice(): void
    {
        $this->building->update(['water_reconnection_fee' => 500]);
        $unit = $this->units->get(3);
        ['lease' => $lease] = Model::withoutEvents(fn () => $this->createTenantWithActiveLease($this->landlord, $unit));
        $meter = Meter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $unit->building_id,
            'unit_id' => $unit->id,
            'status' => 'active',
            'disconnected_at' => now(),
        ]);

        $this->actingAs($this->landlord->fresh())
            ->post(route('meters.reconnect', $meter->id))
            ->assertRedirect();

        $this->assertSame(1, WaterPendingCharge::where('lease_id', $lease->id)->whereNull('applied_at')->count());

        $invoice = Model::withoutEvents(fn () => app(InvoiceService::class)->generateInvoiceForLease($lease->fresh(), now()));
        $this->assertEquals(500, (float) Invoice::find($invoice->id)->water_due);
        $this->assertSame(0, WaterPendingCharge::where('lease_id', $lease->id)->whereNull('applied_at')->count());
    }

    public function test_double_reconnect_does_not_double_charge(): void
    {
        $this->building->update(['water_reconnection_fee' => 500]);
        $unit = $this->units->get(7);
        ['lease' => $lease] = Model::withoutEvents(fn () => $this->createTenantWithActiveLease($this->landlord, $unit));
        $meter = Meter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $unit->building_id,
            'unit_id' => $unit->id,
            'status' => 'active',
            'disconnected_at' => now(),
        ]);

        $this->actingAs($this->landlord->fresh())->post(route('meters.reconnect', $meter->id))->assertRedirect();
        // Second reconnect is a no-op (meter already reconnected) — no extra fee.
        $this->actingAs($this->landlord->fresh())->post(route('meters.reconnect', $meter->id))->assertRedirect();

        $this->assertSame(1, WaterPendingCharge::where('lease_id', $lease->id)->count());
    }

    public function test_invoice_water_due_unchanged_without_pending_charges(): void
    {
        $unit = $this->units->get(4);
        ['lease' => $lease] = Model::withoutEvents(fn () => $this->createTenantWithActiveLease($this->landlord, $unit));

        $invoice = Model::withoutEvents(fn () => app(InvoiceService::class)->generateInvoiceForLease($lease->fresh(), now()));
        $this->assertEquals(0, (float) Invoice::find($invoice->id)->water_due);
    }

    // --- ARREARS + REMINDER ----------------------------------------------

    public function test_overdue_water_invoice_triggers_a_reminder(): void
    {
        $unit = $this->units->get(5);
        ['tenant' => $tenant, 'lease' => $lease] = Model::withoutEvents(fn () => $this->createTenantWithActiveLease($this->landlord, $unit));
        Model::withoutEvents(fn () => Invoice::create([
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'invoice_number' => 'INV-T90-1',
            'due_date' => now()->subDays(10),
            'billing_period_start' => now()->startOfMonth(),
            'rent_due' => 10000,
            'water_due' => 1500,
            'arrears' => 0,
            'total_due' => 11500,
            'amount_paid' => 0,
            'status' => InvoiceStatus::Overdue,
        ]));

        Artisan::call('water:arrears-notify');

        $this->assertTrue(
            Notification::where('recipient_id', $tenant->id)
                ->where('type', Notification::TYPE_WATER_ARREARS)
                ->exists()
        );
    }

    // --- TENANT BANNER ---------------------------------------------------

    public function test_tenant_sees_disconnection(): void
    {
        $unit = $this->units->get(6);
        ['tenant' => $tenant] = Model::withoutEvents(fn () => $this->createTenantWithActiveLease($this->landlord, $unit));
        Meter::factory()->create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $unit->building_id,
            'unit_id' => $unit->id,
            'status' => 'active',
            'disconnected_at' => now(),
            'disconnect_reason' => 'Non-payment',
        ]);

        $props = $this->actingAs($tenant->fresh())
            ->get(route('tenant.water'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertTrue($props['meterDisconnected']);
    }

    // --- CONFIG ----------------------------------------------------------

    public function test_landlord_persists_reconnection_fee(): void
    {
        $this->actingAs($this->landlord->fresh())
            ->put(route('water.settings.update'), [
                'water_billing_type' => 'consumption',
                'water_unit_rate' => 150,
                'water_reconnection_fee' => 750,
            ])
            ->assertRedirect();

        $this->assertEquals(750, (float) PaymentConfiguration::where('landlord_id', $this->landlord->id)->firstOrFail()->water_reconnection_fee);
    }
}
