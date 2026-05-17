<?php

declare(strict_types=1);

namespace Tests\Feature\TenantPortal;

use App\Console\Commands\ExpireStaleLeaseRenewalCounters;
use App\Models\LeaseRenewal;
use App\Models\LeaseRenewalCounterHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-45 LEASE-COUNTER-1/2/3 watchdog suite.
 */
class Phase45LeaseCounterTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private LeaseRenewal $renewal;

    protected function setUp(): void
    {
        parent::setUp();

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        ['tenant' => $this->tenant, 'lease' => $lease] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );

        $this->renewal = LeaseRenewal::create([
            'landlord_id' => $this->landlord->id,
            'lease_id' => $lease->id,
            'proposed_end_date' => now()->addYear()->toDateString(),
            'proposed_rent_amount_cents' => 4_200_000,
            'status' => LeaseRenewal::STATUS_PROPOSED,
            'proposed_at' => now(),
        ]);
    }

    public function test_tenant_counters_proposed_renewal_and_audit_row_is_created(): void
    {
        $response = $this->actingAs($this->tenant)
            ->post(route('tenant.renewals.counter', $this->renewal), [
                'counter_rent_amount_cents' => 3_800_000,
                'counter_end_date' => now()->addMonths(6)->toDateString(),
                'counter_message' => 'I can do 38k for 6 more months.',
            ]);

        $response->assertRedirect();

        $this->renewal->refresh();
        $this->assertSame(LeaseRenewal::STATUS_COUNTER_PROPOSED, $this->renewal->status);
        $this->assertSame(3_800_000, $this->renewal->counter_rent_amount_cents);
        $this->assertNotNull($this->renewal->counter_submitted_at);

        $history = LeaseRenewalCounterHistory::query()
            ->where('lease_renewal_id', $this->renewal->id)
            ->first();
        $this->assertNotNull($history);
        $this->assertSame(LeaseRenewalCounterHistory::ACTION_COUNTERED, $history->action);
        $this->assertSame(3_800_000, $history->rent_amount_cents);
    }

    public function test_tenant_cannot_counter_non_proposed_renewal(): void
    {
        $this->renewal->update(['status' => LeaseRenewal::STATUS_REJECTED]);

        $response = $this->actingAs($this->tenant)
            ->post(route('tenant.renewals.counter', $this->renewal), [
                'counter_rent_amount_cents' => 3_800_000,
                'counter_end_date' => now()->addMonths(6)->toDateString(),
            ]);

        $response->assertStatus(422);
    }

    public function test_landlord_accepts_counter_promotes_counter_values_to_proposed_columns(): void
    {
        $this->renewal->update([
            'status' => LeaseRenewal::STATUS_COUNTER_PROPOSED,
            'counter_rent_amount_cents' => 3_500_000,
            'counter_end_date' => now()->addMonths(6)->toDateString(),
            'counter_submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->landlord)
            ->post(route('landlords.renewals.counter.accept', $this->renewal));

        $response->assertRedirect();

        $this->renewal->refresh();
        $this->assertSame(LeaseRenewal::STATUS_ACCEPTED, $this->renewal->status);
        $this->assertSame(3_500_000, $this->renewal->proposed_rent_amount_cents);

        $history = LeaseRenewalCounterHistory::query()
            ->where('lease_renewal_id', $this->renewal->id)
            ->where('action', LeaseRenewalCounterHistory::ACTION_ACCEPTED)
            ->first();
        $this->assertNotNull($history);
    }

    public function test_landlord_re_proposes_clears_counter_columns_and_flips_status(): void
    {
        $this->renewal->update([
            'status' => LeaseRenewal::STATUS_COUNTER_PROPOSED,
            'counter_rent_amount_cents' => 3_500_000,
            'counter_end_date' => now()->addMonths(6)->toDateString(),
            'counter_submitted_at' => now(),
            'counter_message' => 'I want 35k',
        ]);

        $newEnd = now()->addMonths(9)->toDateString();
        $response = $this->actingAs($this->landlord)
            ->post(route('landlords.renewals.counter.re_propose', $this->renewal), [
                'proposed_rent_amount_cents' => 4_000_000,
                'proposed_end_date' => $newEnd,
                'notes' => 'How about 40k for 9 months?',
            ]);

        $response->assertRedirect();

        $this->renewal->refresh();
        $this->assertSame(LeaseRenewal::STATUS_PROPOSED, $this->renewal->status);
        $this->assertSame(4_000_000, $this->renewal->proposed_rent_amount_cents);
        $this->assertNull($this->renewal->counter_rent_amount_cents);
        $this->assertNull($this->renewal->counter_message);

        $history = LeaseRenewalCounterHistory::query()
            ->where('lease_renewal_id', $this->renewal->id)
            ->where('action', LeaseRenewalCounterHistory::ACTION_RE_PROPOSED)
            ->first();
        $this->assertNotNull($history);
        $this->assertSame(4_000_000, $history->rent_amount_cents);
    }

    public function test_landlord_review_endpoints_reject_non_counter_status(): void
    {
        // renewal is still in 'proposed' from setUp
        $response = $this->actingAs($this->landlord)
            ->post(route('landlords.renewals.counter.accept', $this->renewal));

        $response->assertStatus(422);
    }

    public function test_stranger_landlord_cannot_review_counter(): void
    {
        $this->renewal->update([
            'status' => LeaseRenewal::STATUS_COUNTER_PROPOSED,
            'counter_rent_amount_cents' => 3_500_000,
            'counter_end_date' => now()->addMonths(6)->toDateString(),
            'counter_submitted_at' => now(),
        ]);

        $stranger = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($stranger)
            ->post(route('landlords.renewals.counter.accept', $this->renewal));

        $response->assertForbidden();
    }

    public function test_cron_expires_counters_older_than_14_days(): void
    {
        $this->renewal->update([
            'status' => LeaseRenewal::STATUS_COUNTER_PROPOSED,
            'counter_rent_amount_cents' => 3_500_000,
            'counter_end_date' => now()->addMonths(6)->toDateString(),
            'counter_submitted_at' => now()->subDays(20),
        ]);

        $this->artisan('lease-renewal:expire-stale-counters')->assertExitCode(0);

        $this->renewal->refresh();
        $this->assertSame(LeaseRenewal::STATUS_EXPIRED, $this->renewal->status);

        $expiredHistory = LeaseRenewalCounterHistory::query()
            ->where('lease_renewal_id', $this->renewal->id)
            ->where('action', LeaseRenewalCounterHistory::ACTION_EXPIRED)
            ->first();
        $this->assertNotNull($expiredHistory);
    }

    public function test_cron_leaves_fresh_counters_alone(): void
    {
        $this->renewal->update([
            'status' => LeaseRenewal::STATUS_COUNTER_PROPOSED,
            'counter_rent_amount_cents' => 3_500_000,
            'counter_end_date' => now()->addMonths(6)->toDateString(),
            'counter_submitted_at' => now()->subDays(7),
        ]);

        $this->artisan('lease-renewal:expire-stale-counters')->assertExitCode(0);

        $this->renewal->refresh();
        $this->assertSame(LeaseRenewal::STATUS_COUNTER_PROPOSED, $this->renewal->status);
    }
}
