<?php

declare(strict_types=1);

namespace Tests\Feature\Leases;

use App\Models\Lease;
use App\Models\LeasePause;
use App\Models\Unit;
use App\Models\User;
use App\Services\Lease\LeasePauseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

/**
 * Phase-61 PAUSE-1/2/3: temporary lease pause + auto-resume cron.
 */
class Phase61PauseTest extends TestCase
{
    use RefreshDatabase;

    private function makeLease(): Lease
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);
        $unit = Unit::factory()->create();

        return Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $landlord->id,
            'is_active' => true,
        ]);
    }

    public function test_start_creates_pause_and_flips_lease_inactive(): void
    {
        $lease = $this->makeLease();

        $pause = app(LeasePauseService::class)->start(
            $lease,
            $lease->landlord,
            [
                'pause_start' => now()->addDays(10)->toDateString(),
                'pause_end' => now()->addDays(40)->toDateString(),
                'reason' => LeasePause::REASON_TENANT_HARDSHIP,
            ],
        );

        $this->assertSame(LeasePause::STATUS_ACTIVE, $pause->status);
        $this->assertFalse($lease->fresh()->is_active);
    }

    public function test_cancel_reactivates_lease(): void
    {
        $lease = $this->makeLease();
        $lease->is_active = false;
        $lease->save();

        $pause = LeasePause::create([
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'initiated_by' => $lease->landlord_id,
            'pause_start' => now()->addDays(5)->toDateString(),
            'pause_end' => now()->addDays(30)->toDateString(),
            'reason' => LeasePause::REASON_TENANT_HARDSHIP,
            'status' => LeasePause::STATUS_ACTIVE,
        ]);

        app(LeasePauseService::class)->cancel($pause);

        $this->assertSame(LeasePause::STATUS_CANCELLED, $pause->fresh()->status);
        $this->assertTrue($lease->fresh()->is_active);
    }

    public function test_auto_resume_command_resumes_elapsed_pauses(): void
    {
        $lease = $this->makeLease();
        $lease->is_active = false;
        $lease->save();

        $pause = LeasePause::create([
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'initiated_by' => $lease->landlord_id,
            'pause_start' => now()->subDays(30)->toDateString(),
            'pause_end' => now()->subDay()->toDateString(),
            'reason' => LeasePause::REASON_TENANT_HARDSHIP,
            'status' => LeasePause::STATUS_ACTIVE,
        ]);

        $this->artisan('lease-pause:auto-resume')->assertExitCode(0);

        $pause->refresh();
        $this->assertSame(LeasePause::STATUS_COMPLETED, $pause->status);
        $this->assertTrue($pause->auto_resumed);
        $this->assertTrue($lease->fresh()->is_active);
    }

    public function test_auto_resume_command_skips_in_window_pauses(): void
    {
        $lease = $this->makeLease();
        $lease->is_active = false;
        $lease->save();

        $pause = LeasePause::create([
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'initiated_by' => $lease->landlord_id,
            'pause_start' => now()->subDay()->toDateString(),
            'pause_end' => now()->addDays(10)->toDateString(),
            'reason' => LeasePause::REASON_MUTUAL,
            'status' => LeasePause::STATUS_ACTIVE,
        ]);

        $this->artisan('lease-pause:auto-resume')->assertExitCode(0);

        $this->assertSame(LeasePause::STATUS_ACTIVE, $pause->fresh()->status);
        $this->assertFalse($lease->fresh()->is_active);
    }

    public function test_auto_resume_scheduled_at_0600(): void
    {
        $events = collect(Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'lease-pause:auto-resume'));

        $this->assertNotNull($entry);
        $this->assertSame('0 6 * * *', $entry->expression);
    }

    public function test_route_requires_landlord(): void
    {
        $lease = $this->makeLease();

        $response = $this->actingAs($lease->tenant)
            ->from(route('leases.show', $lease))
            ->post(route('leases.pause', $lease), [
                'pause_start' => now()->addDays(10)->toDateString(),
                'pause_end' => now()->addDays(40)->toDateString(),
                'reason' => LeasePause::REASON_TENANT_HARDSHIP,
            ]);

        $response->assertForbidden();
    }
}
