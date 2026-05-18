<?php

declare(strict_types=1);

namespace Tests\Feature\Leases;

use App\Models\Lease;
use App\Models\Unit;
use App\Models\User;
use App\Services\Lease\LeaseRenewalAutoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

/**
 * Phase-61 RENEWAL-AUTO-1/2/3: auto_renew column + service +
 * lease:auto-renew cron + per-lease toggle.
 */
class Phase61RenewalAutoTest extends TestCase
{
    use RefreshDatabase;

    private function makeLease(array $attrs = []): Lease
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);
        $unit = Unit::factory()->create();

        return Lease::factory()->create(array_merge([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $landlord->id,
            'is_active' => true,
            'auto_renew' => true,
        ], $attrs));
    }

    public function test_auto_renew_column_defaults_true(): void
    {
        $lease = $this->makeLease();
        $this->assertTrue($lease->auto_renew);
    }

    public function test_service_creates_next_period_lease_with_parent_link(): void
    {
        $lease = $this->makeLease([
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => now()->addDays(15)->toDateString(),
        ]);

        $created = app(LeaseRenewalAutoService::class)->scanExpiring(30);

        $this->assertCount(1, $created);
        $next = $created[0];
        $this->assertSame($lease->id, $next->renewed_from_lease_id);
        $this->assertTrue($next->auto_renew);
        $this->assertSame($lease->tenant_id, $next->tenant_id);
        $this->assertSame($lease->unit_id, $next->unit_id);
    }

    public function test_service_skips_opted_out_leases(): void
    {
        $this->makeLease([
            'auto_renew' => false,
            'end_date' => now()->addDays(10)->toDateString(),
        ]);

        $created = app(LeaseRenewalAutoService::class)->scanExpiring(30);

        $this->assertCount(0, $created);
    }

    public function test_service_skips_leases_beyond_window(): void
    {
        $this->makeLease(['end_date' => now()->addDays(60)->toDateString()]);

        $created = app(LeaseRenewalAutoService::class)->scanExpiring(30);

        $this->assertCount(0, $created);
    }

    public function test_dry_run_does_not_create_new_leases(): void
    {
        $this->makeLease(['end_date' => now()->addDays(10)->toDateString()]);

        $before = Lease::count();
        app(LeaseRenewalAutoService::class)->scanExpiring(30, dryRun: true);
        $after = Lease::count();

        $this->assertSame($before, $after);
    }

    public function test_auto_renew_command_scheduled_at_0700(): void
    {
        $events = collect(Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'lease:auto-renew'));

        $this->assertNotNull($entry);
        $this->assertSame('0 7 * * *', $entry->expression);
    }

    public function test_toggle_route_flips_auto_renew(): void
    {
        $lease = $this->makeLease(['auto_renew' => true]);

        $response = $this->actingAs($lease->landlord)
            ->from(route('leases.show', $lease))
            ->patch(route('leases.auto-renew', $lease), ['auto_renew' => false]);

        $response->assertRedirect();
        $this->assertFalse($lease->fresh()->auto_renew);
    }

    public function test_toggle_route_403s_for_non_landlord(): void
    {
        $lease = $this->makeLease();

        $response = $this->actingAs($lease->tenant)
            ->from(route('leases.show', $lease))
            ->patch(route('leases.auto-renew', $lease), ['auto_renew' => false]);

        $response->assertForbidden();
    }
}
