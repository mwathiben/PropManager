<?php

declare(strict_types=1);

namespace Tests\Feature\Lease;

use App\Models\Lease;
use App\Models\RentEscalation;
use App\Models\RentHistory;
use App\Models\User;
use App\Services\Lease\LeaseLifecycleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-83 LIFECYCLE-VIEW: lease Show page + timeline aggregation.
 */
class Phase83LifecycleViewTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $bundle = Model::withoutEvents(fn () => $this->createTenantWithActiveLease($this->landlord, $setup['units']->get(0)));
        $this->tenant = $bundle['tenant'];
        $this->lease = $bundle['lease'];
    }

    public function test_landlord_gets_lease_show_component(): void
    {
        $this->actingAs($this->landlord)
            ->get(route('leases.show', $this->lease->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Leases/Show')
                ->where('lease.id', $this->lease->id)
                ->has('timeline')
            );
    }

    public function test_tenant_is_redirected(): void
    {
        $this->actingAs($this->tenant)
            ->get(route('leases.show', $this->lease->id))
            ->assertRedirect(route('tenants.show', $this->lease->tenant_id));
    }

    public function test_cross_landlord_denied(): void
    {
        $other = Model::withoutEvents(fn () => User::factory()->create(['role' => 'landlord']));
        $resp = $this->actingAs($other)->get(route('leases.show', $this->lease->id));
        $this->assertContains($resp->status(), [403, 404]);
    }

    public function test_timeline_merges_and_sorts_events(): void
    {
        Model::withoutEvents(function () {
            RentHistory::create([
                'lease_id' => $this->lease->id,
                'old_amount' => 10000,
                'new_amount' => 11000,
                'effective_date' => now()->subMonths(2)->toDateString(),
                'reason' => 'past change',
                'notification_sent' => true,
            ]);
            RentEscalation::factory()->create([
                'lease_id' => $this->lease->id,
                'landlord_id' => $this->landlord->id,
                'effective_date' => now()->addMonth()->toDateString(),
            ]);
        });

        $timeline = app(LeaseLifecycleService::class)->timeline($this->lease);

        $types = array_column($timeline, 'type');
        $this->assertContains('rent_change', $types);
        $this->assertContains('escalation', $types);
        // Sorted newest-first: the future escalation precedes the past rent change.
        $this->assertSame('escalation', $timeline[0]['type']);
    }
}
