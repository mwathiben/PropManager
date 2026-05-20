<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Models\ProductEvent;
use App\Models\Referral;
use App\Models\User;
use App\Services\Growth\CohortRetentionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Phase-66 COHORT-RETENTION CI: super-admin guard on the cross-tenant
 * read, landlord-scoped isolation, delta-vs-organic math, and the
 * insufficient_sample flag.
 */
class Phase66CohortRetentionServiceTest extends TestCase
{
    use RefreshDatabase;

    private int $refSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        // Small threshold keeps the fixtures (and observer overhead) light.
        config(['growth.cohort.min_sample' => 3]);
    }

    private function service(): CohortRetentionService
    {
        return app(CohortRetentionService::class);
    }

    /**
     * Create $count users in the current month for a source, $active of
     * whom log a product event that month (driving offset-0 retention).
     *
     * @return list<int> the created user ids
     */
    private function cohort(string $source, int $count, int $active): array
    {
        $thisMonth = now()->startOfMonth();
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $user = User::factory()->create([
                'acquisition_source' => $source,
                'created_at' => $thisMonth,
            ]);
            $ids[] = $user->id;
            if ($i < $active) {
                ProductEvent::create([
                    'user_id' => $user->id,
                    'event_name' => 'session.start',
                    'created_at' => $thisMonth,
                ]);
            }
        }

        return $ids;
    }

    private function source(array $result, string $name): ?array
    {
        foreach ($result['sources'] as $row) {
            if ($row['source'] === $name) {
                return $row;
            }
        }

        return null;
    }

    public function test_global_comparison_blocks_non_super_admin(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord);
        $this->expectException(AuthorizationException::class);
        $this->service()->sourceComparison(12);
    }

    public function test_global_comparison_blocks_guests(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->service()->sourceComparison(12);
    }

    public function test_super_admin_global_comparison_computes_delta_and_baseline(): void
    {
        $this->cohort('organic', 4, 1);   // retention[0] = 0.25
        $this->cohort('referral', 4, 3);  // retention[0] = 0.75

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin);

        $result = $this->service()->sourceComparison(12);

        $this->assertSame(12, $result['month_range']);
        $this->assertSame(3, $result['min_sample']);

        $organic = $this->source($result, 'organic');
        $referral = $this->source($result, 'referral');

        $this->assertEqualsWithDelta(0.25, $organic['retention'][0], 0.0001);
        $this->assertEqualsWithDelta(0.75, $referral['retention'][0], 0.0001);

        // organic is its own baseline → zero delta; referral is +0.50.
        $this->assertEqualsWithDelta(0.0, $organic['delta_vs_organic'][0], 0.0001);
        $this->assertEqualsWithDelta(0.50, $referral['delta_vs_organic'][0], 0.0001);

        // organic is listed first (it is the baseline).
        $this->assertSame('organic', $result['sources'][0]['source']);
    }

    public function test_insufficient_sample_flag(): void
    {
        $this->cohort('organic', 4, 2);  // 4 >= 3 → sufficient
        $this->cohort('paid', 2, 1);     // 2 < 3 → insufficient

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin);

        $result = $this->service()->sourceComparison(12);

        $this->assertFalse($this->source($result, 'organic')['insufficient_sample']);
        $this->assertTrue($this->source($result, 'paid')['insufficient_sample']);
    }

    public function test_landlord_variant_excludes_other_landlords(): void
    {
        $landlordA = User::factory()->create(['role' => 'landlord']);
        $landlordB = User::factory()->create(['role' => 'landlord']);

        $aReferred = $this->cohort('referral', 2, 2);
        $bReferred = $this->cohort('referral', 2, 1);

        foreach ($aReferred as $id) {
            $this->referral($landlordA->id, $id);
        }
        foreach ($bReferred as $id) {
            $this->referral($landlordB->id, $id);
        }

        $result = $this->service()->sourceComparisonForLandlord($landlordA->fresh());

        $referral = $this->source($result, 'referral');
        $this->assertNotNull($referral);
        // Only landlord A's two referred users — never B's.
        $this->assertSame(2, $referral['total_size']);
    }

    public function test_ops_route_is_super_admin_gated(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)
            ->get(route('ops.growth.cohort-retention.index'))
            ->assertForbidden();
    }

    public function test_super_admin_views_cohort_dashboard(): void
    {
        $this->cohort('organic', 4, 2);
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->get(route('ops.growth.cohort-retention.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ops/Growth/CohortRetention')
                ->where('min_sample', 3)
                ->has('source_comparison')
            );
    }

    private function referral(int $referrerId, int $referredId): void
    {
        $this->refSeq++;
        Referral::create([
            'referrer_user_id' => $referrerId,
            'referred_user_id' => $referredId,
            'referral_code' => str_pad((string) $this->refSeq, 8, '0', STR_PAD_LEFT),
            'status' => Referral::STATUS_ATTRIBUTED,
            'attributed_at' => now(),
        ]);
    }
}
