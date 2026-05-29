<?php

declare(strict_types=1);

namespace Tests\Feature\Workflow;

use App\Enums\MoveOutStatus;
use App\Events\OccupancyTargetBreached;
use App\Events\VacancyDetected;
use App\Models\LandlordTask;
use App\Models\MoveOut;
use App\Models\User;
use App\Services\OccupancyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-29 WF-VACANCY-1/2/3 watchdog suite.
 */
class Phase29VacancyTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private $setup;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->setup = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setup['landlord'];
    }

    public function test_by_building_returns_zero_occupancy_when_no_active_leases(): void
    {
        $rows = app(OccupancyService::class)->byBuilding($this->landlord);
        $this->assertSame(1, $rows->count());
        $this->assertSame(0.0, $rows->first()['occupancy_rate_pct']);
        $this->assertSame(8, $rows->first()['total_units']);
        $this->assertSame(8, $rows->first()['vacant_units']);
    }

    public function test_by_building_returns_partial_occupancy(): void
    {
        $this->createTenantWithActiveLease($this->landlord, $this->setup['units']->first());
        $this->createTenantWithActiveLease($this->landlord, $this->setup['units']->get(1));

        $row = app(OccupancyService::class)->byBuilding($this->landlord)->first();
        $this->assertSame(2, $row['occupied_units']);
        $this->assertSame(6, $row['vacant_units']);
        $this->assertEqualsWithDelta(25.0, $row['occupancy_rate_pct'], 0.01);
    }

    public function test_audit_command_emits_no_breach_when_no_target_set(): void
    {
        Event::fake([OccupancyTargetBreached::class]);
        $this->artisan('occupancy:audit')->assertSuccessful();
        Event::assertNotDispatched(OccupancyTargetBreached::class);
    }

    public function test_audit_command_fires_breach_when_below_target(): void
    {
        Event::fake([OccupancyTargetBreached::class]);
        $this->setup['building']->update(['target_occupancy_rate' => 85.00]);

        $this->artisan('occupancy:audit')->assertSuccessful();

        Event::assertDispatched(
            OccupancyTargetBreached::class,
            fn (OccupancyTargetBreached $e) => $e->building->id === $this->setup['building']->id
                && $e->targetRate === 85.0
                && $e->currentRate < 85.0,
        );
    }

    public function test_audit_command_does_not_fire_breach_when_above_target(): void
    {
        Event::fake([OccupancyTargetBreached::class]);
        $this->setup['building']->update(['target_occupancy_rate' => 10.00]);
        $this->createTenantWithActiveLease($this->landlord, $this->setup['units']->first());

        $this->artisan('occupancy:audit')->assertSuccessful();
        Event::assertNotDispatched(OccupancyTargetBreached::class);
    }

    public function test_audit_command_idempotency_within_same_month(): void
    {
        Event::fake([OccupancyTargetBreached::class]);
        $this->setup['building']->update(['target_occupancy_rate' => 85.00]);

        $this->artisan('occupancy:audit')->assertSuccessful();
        $this->artisan('occupancy:audit')->assertSuccessful();

        Event::assertDispatchedTimes(OccupancyTargetBreached::class, 1);
    }

    public function test_move_out_completed_fires_vacancy_detected_when_no_future_lease(): void
    {
        Event::fake([VacancyDetected::class]);
        ['lease' => $lease] = $this->createTenantWithActiveLease(
            $this->landlord,
            $this->setup['units']->first(),
        );

        $moveOut = MoveOut::create([
            'landlord_id' => $this->landlord->id,
            'lease_id' => $lease->id,
            'notice_date' => now()->subDays(30)->toDateString(),
            'intended_move_out_date' => now()->subDay()->toDateString(),
            'status' => MoveOutStatus::InspectionComplete,
        ]);
        $moveOut->update(['status' => MoveOutStatus::Completed]);

        Event::assertDispatched(VacancyDetected::class);
    }

    public function test_move_out_completed_does_not_fire_when_future_lease_exists(): void
    {
        Event::fake([VacancyDetected::class]);
        ['lease' => $oldLease] = $this->createTenantWithActiveLease(
            $this->landlord,
            $this->setup['units']->first(),
        );

        // Future lease on the same unit.
        $futureTenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $this->landlord->id]);
        \App\Models\Lease::create([
            'unit_id' => $oldLease->unit_id,
            'tenant_id' => $futureTenant->id,
            'landlord_id' => $this->landlord->id,
            'rent_amount' => 25000,
            'deposit_amount' => 25000,
            'start_date' => now()->addMonth(),
            'is_active' => false,
            'wallet_balance' => 0,
        ]);

        $moveOut = MoveOut::create([
            'landlord_id' => $this->landlord->id,
            'lease_id' => $oldLease->id,
            'notice_date' => now()->subDays(30)->toDateString(),
            'intended_move_out_date' => now()->subDay()->toDateString(),
            'status' => MoveOutStatus::InspectionComplete,
        ]);
        $moveOut->update(['status' => MoveOutStatus::Completed]);

        Event::assertNotDispatched(VacancyDetected::class);
    }

    public function test_vacancy_listener_creates_high_priority_list_unit_task(): void
    {
        ['lease' => $lease] = $this->createTenantWithActiveLease(
            $this->landlord,
            $this->setup['units']->first(),
        );

        $moveOut = MoveOut::create([
            'landlord_id' => $this->landlord->id,
            'lease_id' => $lease->id,
            'notice_date' => now()->subDays(30)->toDateString(),
            'intended_move_out_date' => now()->subDay()->toDateString(),
            'status' => MoveOutStatus::InspectionComplete,
        ]);
        $moveOut->update(['status' => MoveOutStatus::Completed]);

        $task = LandlordTask::where('source_workflow', 'WF-VACANCY-2')->first();
        $this->assertNotNull($task);
        $this->assertSame('list_unit', $task->task_type);
        $this->assertSame('high', $task->priority);
        $this->assertSame(\App\Models\Unit::class, $task->related_to_type);
        $this->assertSame($lease->unit_id, $task->related_to_id);
    }

    public function test_schedule_includes_occupancy_audit_at_06_30(): void
    {
        $entry = collect(Schedule::events())
            ->first(fn ($e) => str_contains((string) $e->command, 'occupancy:audit'));

        $this->assertNotNull($entry);
        $this->assertSame('30 6 * * *', $entry->expression);
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }
}
