<?php

declare(strict_types=1);

namespace Tests\Feature\Workflow;

use App\Events\LeaseRenewalApproaching;
use App\Models\LeaseRenewal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-29 WF-LEASE-RENEW-1/2/3 watchdog suite.
 */
class Phase29LeaseRenewTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private $lease;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        ['tenant' => $this->tenant, 'lease' => $this->lease] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );
    }

    public function test_60_day_bucket_fires_event_at_exactly_60_days(): void
    {
        Event::fake([LeaseRenewalApproaching::class]);
        $this->lease->update(['end_date' => CarbonImmutable::now()->addDays(60)->toDateString()]);

        $this->artisan('leases:scan-renewals')->assertSuccessful();

        Event::assertDispatched(
            LeaseRenewalApproaching::class,
            fn (LeaseRenewalApproaching $e) => $e->lease->id === $this->lease->id && $e->bucketDays === 60,
        );
    }

    public function test_30_day_bucket_fires_event(): void
    {
        Event::fake([LeaseRenewalApproaching::class]);
        $this->lease->update(['end_date' => CarbonImmutable::now()->addDays(30)->toDateString()]);

        $this->artisan('leases:scan-renewals')->assertSuccessful();

        Event::assertDispatched(LeaseRenewalApproaching::class);
    }

    public function test_7_day_bucket_fires_event(): void
    {
        Event::fake([LeaseRenewalApproaching::class]);
        $this->lease->update(['end_date' => CarbonImmutable::now()->addDays(7)->toDateString()]);

        $this->artisan('leases:scan-renewals')->assertSuccessful();

        Event::assertDispatched(LeaseRenewalApproaching::class);
    }

    public function test_non_bucket_day_does_not_fire(): void
    {
        Event::fake([LeaseRenewalApproaching::class]);
        $this->lease->update(['end_date' => CarbonImmutable::now()->addDays(45)->toDateString()]);

        $this->artisan('leases:scan-renewals')->assertSuccessful();

        Event::assertNotDispatched(LeaseRenewalApproaching::class);
    }

    public function test_idempotency_within_same_month(): void
    {
        Event::fake([LeaseRenewalApproaching::class]);
        $this->lease->update(['end_date' => CarbonImmutable::now()->addDays(30)->toDateString()]);

        $this->artisan('leases:scan-renewals')->assertSuccessful();
        $this->artisan('leases:scan-renewals')->assertSuccessful();

        Event::assertDispatchedTimes(LeaseRenewalApproaching::class, 1);
    }

    public function test_inactive_lease_never_fires(): void
    {
        Event::fake([LeaseRenewalApproaching::class]);
        $this->lease->update([
            'end_date' => CarbonImmutable::now()->addDays(30)->toDateString(),
            'is_active' => false,
        ]);

        $this->artisan('leases:scan-renewals')->assertSuccessful();
        Event::assertNotDispatched(LeaseRenewalApproaching::class);
    }

    public function test_landlord_can_propose_renewal_on_own_lease(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post(route('leases.renewals.store', ['lease' => $this->lease->id]), [
                'proposed_end_date' => CarbonImmutable::now()->addYear()->toDateString(),
                'proposed_rent_amount_cents' => 3_000_000,
                'notes' => 'Annual renewal',
            ]);

        $response->assertRedirect();
        $this->assertSame(1, LeaseRenewal::where('lease_id', $this->lease->id)->count());
        $renewal = LeaseRenewal::where('lease_id', $this->lease->id)->first();
        $this->assertSame(LeaseRenewal::STATUS_PROPOSED, $renewal->status);
    }

    public function test_landlord_cannot_propose_second_open_renewal(): void
    {
        $payload = [
            'proposed_end_date' => CarbonImmutable::now()->addYear()->toDateString(),
            'proposed_rent_amount_cents' => 3_000_000,
        ];

        $this->actingAs($this->landlord)
            ->post(route('leases.renewals.store', ['lease' => $this->lease->id]), $payload)
            ->assertRedirect();

        $this->actingAs($this->landlord)
            ->post(route('leases.renewals.store', ['lease' => $this->lease->id]), $payload)
            ->assertStatus(422);
    }

    public function test_other_landlord_cannot_propose_renewal_on_foreign_lease(): void
    {
        $other = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($other)
            ->post(route('leases.renewals.store', ['lease' => $this->lease->id]), [
                'proposed_end_date' => CarbonImmutable::now()->addYear()->toDateString(),
                'proposed_rent_amount_cents' => 3_000_000,
            ])
            ->assertForbidden();
    }

    public function test_tenant_can_accept_open_renewal(): void
    {
        $renewal = $this->createProposedRenewal();

        $this->actingAs($this->tenant)
            ->post(route('tenant.renewals.accept', ['renewal' => $renewal->id]))
            ->assertRedirect();

        $this->assertSame(LeaseRenewal::STATUS_ACCEPTED, $renewal->fresh()->status);
        $this->assertNotNull($renewal->fresh()->responded_at);
    }

    public function test_tenant_cannot_respond_to_other_tenants_renewal(): void
    {
        $otherSetup = $this->createLandlordWithFullSetup();
        ['lease' => $otherLease] = $this->createTenantWithActiveLease(
            $otherSetup['landlord'],
            $otherSetup['units']->first(),
        );
        $renewal = LeaseRenewal::create([
            'landlord_id' => $otherSetup['landlord']->id,
            'lease_id' => $otherLease->id,
            'proposed_end_date' => CarbonImmutable::now()->addYear()->toDateString(),
            'proposed_rent_amount_cents' => 3_000_000,
            'status' => LeaseRenewal::STATUS_PROPOSED,
            'proposed_at' => now(),
        ]);

        $this->actingAs($this->tenant)
            ->post(route('tenant.renewals.accept', ['renewal' => $renewal->id]))
            ->assertForbidden();
    }

    public function test_confirm_writes_lease_end_date_and_rent_atomically(): void
    {
        $renewal = $this->createProposedRenewal();
        $renewal->update(['status' => LeaseRenewal::STATUS_ACCEPTED, 'responded_at' => now()]);
        $newEnd = CarbonImmutable::parse($renewal->proposed_end_date)->toDateString();

        $this->actingAs($this->landlord)
            ->post(route('renewals.confirm', ['renewal' => $renewal->id]))
            ->assertRedirect();

        $this->lease->refresh();
        $this->assertSame($newEnd, $this->lease->end_date->toDateString());
        $this->assertEqualsWithDelta(30000.00, (float) $this->lease->rent_amount, 0.01);
        $this->assertSame(LeaseRenewal::STATUS_CONFIRMED, $renewal->fresh()->status);
    }

    public function test_confirm_rejected_when_renewal_not_accepted(): void
    {
        $renewal = $this->createProposedRenewal();

        $this->actingAs($this->landlord)
            ->post(route('renewals.confirm', ['renewal' => $renewal->id]))
            ->assertStatus(422);
    }

    public function test_renewal_respond_ability_reflects_open_renewal_state(): void
    {
        $this->actingAs($this->tenant)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('auth.tenant_abilities.renewal:respond', false));

        $this->createProposedRenewal();

        $this->actingAs($this->tenant)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('auth.tenant_abilities.renewal:respond', true));
    }

    public function test_schedule_includes_leases_scan_renewals_at_07_30_nairobi(): void
    {
        $entry = collect(Schedule::events())
            ->first(fn ($e) => str_contains((string) $e->command, 'leases:scan-renewals'));

        $this->assertNotNull($entry);
        $this->assertSame('30 7 * * *', $entry->expression);
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    private function createProposedRenewal(): LeaseRenewal
    {
        return LeaseRenewal::create([
            'landlord_id' => $this->landlord->id,
            'lease_id' => $this->lease->id,
            'proposed_end_date' => CarbonImmutable::now()->addYear()->toDateString(),
            'proposed_rent_amount_cents' => 3_000_000,
            'status' => LeaseRenewal::STATUS_PROPOSED,
            'proposed_at' => now(),
        ]);
    }
}
