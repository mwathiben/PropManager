<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\KycSubmissionStatus;
use App\Models\Building;
use App\Models\KycRequirement;
use App\Models\Lease;
use App\Models\Property;
use App\Models\TenantKycSubmission;
use App\Models\Unit;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * M2 decomposition safety net: characterizes the tenant-KYC completion
 * aggregation (getTenantKycStats) BEFORE it is extracted out of
 * DashboardService. This method had no dedicated test, so these
 * assertions are both the extraction guard and new compliance coverage.
 */
class DashboardKycStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::create([
            'name' => 'KYC Property', 'address' => '1 KYC St',
            'type' => 'apartment', 'landlord_id' => $this->landlord->id,
        ]);
        $this->building = Building::create([
            'property_id' => $property->id, 'name' => 'Block A',
            'total_floors' => 1, 'units_per_floor' => 2,
            'landlord_id' => $this->landlord->id, 'building_type' => 'residential_apartment',
        ]);
    }

    private function activeLease(string $unitNo): array
    {
        $unit = Unit::create([
            'building_id' => $this->building->id, 'unit_number' => $unitNo, 'floor_number' => 1,
            'status' => 'occupied', 'target_rent' => 10000, 'landlord_id' => $this->landlord->id,
        ]);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $this->landlord->id]);
        $lease = Lease::create([
            'unit_id' => $unit->id, 'tenant_id' => $tenant->id, 'landlord_id' => $this->landlord->id,
            'rent_amount' => 10000, 'deposit_amount' => 10000, 'start_date' => now()->subMonths(2),
            'is_active' => true, 'wallet_balance' => 0,
        ]);

        return [$lease, $tenant];
    }

    private function stats(Collection $leaseIds): array
    {
        return app(DashboardService::class)->getTenantKycStats($leaseIds);
    }

    public function test_empty_lease_set_returns_zeroed_stats(): void
    {
        $this->assertSame(
            ['total' => 0, 'complete' => 0, 'incomplete' => 0, 'rate' => 0],
            $this->stats(collect()),
        );
    }

    public function test_completion_is_per_tenant_against_required_requirements(): void
    {
        [$leaseA, $tenantA] = $this->activeLease('U1');
        [$leaseB] = $this->activeLease('U2');

        // One required, active, landlord-wide requirement (building_id null
        // => applies to every tenant of this landlord).
        $req = KycRequirement::factory()->forLandlord($this->landlord)->create([
            'is_required' => true, 'is_active' => true, 'building_id' => null,
        ]);

        // Tenant A satisfies it (approved); tenant B has not submitted.
        TenantKycSubmission::factory()->create([
            'user_id' => $tenantA->id, 'landlord_id' => $this->landlord->id,
            'requirement_id' => $req->id, 'status' => KycSubmissionStatus::Approved,
        ]);

        $stats = $this->stats(collect([$leaseA->id, $leaseB->id]));

        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['complete']);
        $this->assertSame(1, $stats['incomplete']);
        $this->assertEqualsWithDelta(50, $stats['rate'], 0.001);
    }

    public function test_tenant_with_no_applicable_requirements_counts_as_complete(): void
    {
        [$leaseA] = $this->activeLease('U1');
        // No requirements exist at all -> the tenant is vacuously complete.
        $stats = $this->stats(collect([$leaseA->id]));

        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, $stats['complete']);
        $this->assertSame(0, $stats['incomplete']);
        $this->assertEqualsWithDelta(100, $stats['rate'], 0.001);
    }
}
