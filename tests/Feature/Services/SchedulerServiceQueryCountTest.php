<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\NotificationSchedule;
use App\Services\SchedulerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * PERF-P5, PERF-P6 regression: getEligibleTenants and getTenatsWithArrears
 * previously returned bare User collections; the per-tenant loop then fired
 * one $tenant->leases()->...->first() query each. For a landlord with N
 * tenants this was 1 + N queries every 5 minutes. Now eager-loads the
 * matching `leases` relation so the loop reads from the in-memory collection.
 */
class SchedulerServiceQueryCountTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_get_eligible_tenants_eager_loads_active_leases_in_one_extra_query(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];

        // 5 tenants, each with an active lease.
        for ($i = 0; $i < 5; $i++) {
            $unit = $setup['units']->get($i);
            if ($unit) {
                $this->createTenantWithActiveLease($landlord, $unit);
            }
        }

        $schedule = NotificationSchedule::create([
            'landlord_id' => $landlord->id,
            'name' => 'Rent reminder',
            'type' => 'rent_reminder',
            'trigger' => 'days_before_due',
            'days_offset' => 5,
            'channels' => ['email'],
            'is_active' => true,
        ]);

        $service = app(SchedulerService::class);

        DB::enableQueryLog();
        $tenants = $service->getEligibleTenants($schedule);
        // Simulate what processRentReminders does: read $tenant->leases->first()
        foreach ($tenants as $tenant) {
            $tenant->leases->first();
        }
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 1 query for users + 1 query for the eager-loaded leases relation
        // = 2 queries total regardless of N tenants. Pre-fix: 1 + N.
        $this->assertLessThanOrEqual(
            2,
            $queryCount,
            "Expected ≤2 queries (users + eager leases), got {$queryCount} — N+1 likely regressed"
        );
        $this->assertGreaterThan(0, $tenants->count(), 'Test seeded tenants — should not be empty');
        $this->assertTrue(
            $tenants->first()->relationLoaded('leases'),
            'leases relation must be eager-loaded'
        );
    }
}
