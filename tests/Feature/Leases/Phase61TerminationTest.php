<?php

declare(strict_types=1);

namespace Tests\Feature\Leases;

use App\Events\LeaseTerminationInitiated;
use App\Exceptions\ShortNoticeException;
use App\Models\Lease;
use App\Models\LeaseTermination;
use App\Models\Unit;
use App\Models\User;
use App\Services\Lease\LeaseTerminationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Phase-61 TERMINATION-1/2/3: lease termination service + route.
 */
class Phase61TerminationTest extends TestCase
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
            'start_date' => now()->subYear(),
            'end_date' => now()->addYear(),
        ]);
    }

    public function test_service_initiates_termination_with_pending_status(): void
    {
        Event::fake();
        $lease = $this->makeLease();

        $termination = app(LeaseTerminationService::class)->initiate(
            $lease,
            $lease->landlord,
            [
                'termination_reason' => LeaseTermination::REASON_MUTUAL,
                'termination_date' => now()->addDays(45)->toDateString(),
                'reason_text' => 'agreed early end',
            ],
        );

        $this->assertSame(LeaseTermination::STATUS_PENDING, $termination->status);
        $this->assertSame($lease->id, $termination->lease_id);
        Event::assertDispatched(LeaseTerminationInitiated::class);
    }

    public function test_service_rejects_short_notice(): void
    {
        $lease = $this->makeLease();

        $this->expectException(ShortNoticeException::class);
        app(LeaseTerminationService::class)->initiate(
            $lease,
            $lease->landlord,
            [
                'termination_reason' => LeaseTermination::REASON_MUTUAL,
                'termination_date' => now()->addDays(5)->toDateString(),
            ],
        );
    }

    public function test_complete_flips_lease_inactive_and_sets_end_date(): void
    {
        $lease = $this->makeLease();
        $termination = LeaseTermination::create([
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'initiated_by' => $lease->landlord_id,
            'termination_reason' => LeaseTermination::REASON_MUTUAL,
            'termination_date' => now()->addDays(60)->toDateString(),
            'notice_given_at' => now(),
            'status' => LeaseTermination::STATUS_ACKNOWLEDGED,
        ]);

        app(LeaseTerminationService::class)->complete($termination);

        $lease->refresh();
        $this->assertFalse($lease->is_active);
        $this->assertSame(
            $termination->termination_date->toDateString(),
            $lease->end_date->toDateString(),
        );
    }

    public function test_acknowledge_dispute_withdraw_update_status(): void
    {
        $lease = $this->makeLease();
        $termination = LeaseTermination::create([
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'initiated_by' => $lease->landlord_id,
            'termination_reason' => LeaseTermination::REASON_MUTUAL,
            'termination_date' => now()->addDays(60)->toDateString(),
            'notice_given_at' => now(),
            'status' => LeaseTermination::STATUS_PENDING,
        ]);

        $service = app(LeaseTerminationService::class);

        $service->acknowledge($termination);
        $this->assertSame(LeaseTermination::STATUS_ACKNOWLEDGED, $termination->fresh()->status);
        $this->assertNotNull($termination->fresh()->acknowledged_at);

        $service->dispute($termination);
        $this->assertSame(LeaseTermination::STATUS_DISPUTED, $termination->fresh()->status);

        $service->withdraw($termination);
        $this->assertSame(LeaseTermination::STATUS_WITHDRAWN, $termination->fresh()->status);
    }

    public function test_route_requires_landlord_or_tenant_party(): void
    {
        $lease = $this->makeLease();
        $stranger = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($stranger)
            ->post(route('leases.terminate', $lease), [
                'termination_reason' => LeaseTermination::REASON_MUTUAL,
                'termination_date' => now()->addDays(45)->toDateString(),
            ]);

        $response->assertForbidden();
    }

    public function test_route_flashes_error_on_short_notice(): void
    {
        $lease = $this->makeLease();

        $response = $this->actingAs($lease->landlord)
            ->from(route('leases.show', $lease))
            ->post(route('leases.terminate', $lease), [
                'termination_reason' => LeaseTermination::REASON_MUTUAL,
                'termination_date' => now()->addDays(2)->toDateString(),
            ]);

        // ValidationException's after:today check passes for +2 days,
        // but the NoticePeriodValidator rejects (need 30 days).
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('error');
    }

    public function test_route_persists_termination_on_success(): void
    {
        $lease = $this->makeLease();

        $response = $this->actingAs($lease->landlord)
            ->from(route('leases.show', $lease))
            ->post(route('leases.terminate', $lease), [
                'termination_reason' => LeaseTermination::REASON_MUTUAL,
                'termination_date' => now()->addDays(45)->toDateString(),
                'reason_text' => 'mutual agreement',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('lease_terminations', [
            'lease_id' => $lease->id,
            'status' => LeaseTermination::STATUS_PENDING,
        ]);
    }
}
