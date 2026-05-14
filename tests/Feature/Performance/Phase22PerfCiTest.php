<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-22 PERF-CI-1 + PERF-CI-2: performance budget gates.
 *
 * PERF-CI-1 — slow-query budget: exercise the hot endpoints with a
 * DB::listen collector and fail if any single query is slow. CI timing
 * is noisier than prod, so the budget is generous on purpose — the goal
 * is catching an accidental un-indexed scan or a lost eager-load (a 10x
 * regression), not micro-optimisation.
 *
 * PERF-CI-2 — query-count budget: PerformanceHardeningTest (Phase-15)
 * pins a 20-query cap for ONE endpoint. This extends the pattern to the
 * other hot index endpoints, and adds the constant-under-N assertion —
 * the real N+1 catch, because an absolute cap alone can hide slow
 * linear growth.
 *
 * Fixture note: ALL datasets are seeded BEFORE any actingAs call.
 * PropManager's TenantScope stamps landlord_id from the auth context on
 * create, so seeding a second landlord's data while a first landlord is
 * "acting" would mis-stamp the rows. Seed first, authenticate second.
 */
class Phase22PerfCiTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    /**
     * Generous per-query budget. Catches a 10x regression (un-indexed
     * scan, lost eager-load), not CI scheduling jitter.
     */
    private const SLOW_QUERY_BUDGET_MS = 500;

    /**
     * Web Inertia routes carry a small, N-independent query jitter:
     * HandleInertiaRequests::getNavBadges array_filters zero-count
     * badges, so the exact query count shifts by ±1-2 depending on
     * which badges are non-zero. The "constant under N" contract is
     * "does not GROW with N" — a 6x row increase that adds ≤ this many
     * queries is benign; an N+1 would add ~one query per extra row.
     */
    private const QUERY_JITTER = 3;

    /**
     * Seed a landlord with a full property setup and N invoices on the
     * first unit's lease. Must be called before any actingAs.
     */
    private function seedLandlordWithInvoices(int $invoiceCount): User
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        $tenantSetup = $this->createTenantWithActiveLease($landlord, $setup['units']->first());

        Invoice::factory()->count($invoiceCount)->create([
            'landlord_id' => $landlord->id,
            'lease_id' => $tenantSetup['lease']->id,
        ]);

        return $landlord;
    }

    /**
     * Seed a landlord with N tenants (one active lease per unit).
     * Must be called before any actingAs.
     */
    private function seedLandlordWithTenants(int $tenantCount): User
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        foreach ($setup['units']->take($tenantCount) as $unit) {
            $this->createTenantWithActiveLease($landlord, $unit);
        }

        return $landlord;
    }

    /**
     * Count the queries a request issues.
     */
    private function countQueries(callable $request): int
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_hot_web_endpoints_have_no_slow_queries(): void
    {
        // PERF-CI-1: 20 invoices is enough that a missing index or a
        // lost eager-load would show up as a slow query.
        $landlord = $this->seedLandlordWithInvoices(20);

        $slow = [];
        DB::listen(function ($query) use (&$slow): void {
            if ($query->time > self::SLOW_QUERY_BUDGET_MS) {
                $slow[] = round($query->time, 1).'ms : '.substr($query->sql, 0, 120);
            }
        });

        foreach (['/invoices', '/tenants'] as $url) {
            $this->actingAs($landlord)->get($url)->assertSuccessful();
        }

        $this->assertSame(
            [],
            $slow,
            'PERF-CI-1: hot endpoints must have no query slower than '.self::SLOW_QUERY_BUDGET_MS.'ms. Offenders: '.implode(' | ', $slow),
        );
    }

    public function test_api_invoice_index_has_no_slow_queries(): void
    {
        $landlord = $this->seedLandlordWithInvoices(20);
        Sanctum::actingAs($landlord, ['landlord:manage']);

        $slow = [];
        DB::listen(function ($query) use (&$slow): void {
            if ($query->time > self::SLOW_QUERY_BUDGET_MS) {
                $slow[] = round($query->time, 1).'ms : '.substr($query->sql, 0, 120);
            }
        });

        $this->getJson('/api/v1/landlord/invoices?per_page=20')->assertOk();

        $this->assertSame([], $slow, 'PERF-CI-1: the API invoice index must have no slow queries. Offenders: '.implode(' | ', $slow));
    }

    public function test_api_invoice_index_query_count_is_constant_under_n(): void
    {
        // PERF-CI-2: the constant-under-N check is the real N+1 catch.
        // Seed BOTH landlords first (see the fixture note), then measure.
        $small = $this->seedLandlordWithInvoices(5);
        $large = $this->seedLandlordWithInvoices(30);

        $smallCount = $this->countQueries(function () use ($small): void {
            Sanctum::actingAs($small, ['landlord:manage']);
            $this->getJson('/api/v1/landlord/invoices?per_page=50')->assertOk();
        });

        $largeCount = $this->countQueries(function () use ($large): void {
            Sanctum::actingAs($large, ['landlord:manage']);
            $this->getJson('/api/v1/landlord/invoices?per_page=50')->assertOk();
        });

        $this->assertLessThanOrEqual(
            $smallCount + self::QUERY_JITTER,
            $largeCount,
            "PERF-CI-2: the API invoice index must be O(1) in row count — saw {$smallCount} queries for 5 invoices vs {$largeCount} for 30. ".
            'A 6x row increase adding more than '.self::QUERY_JITTER.' queries is an N+1.',
        );
        $this->assertLessThan(20, $largeCount, "PERF-CI-2: absolute query-count cap — saw {$largeCount}.");
    }

    public function test_web_invoice_index_query_count_is_constant_under_n(): void
    {
        $small = $this->seedLandlordWithInvoices(5);
        $large = $this->seedLandlordWithInvoices(30);

        $smallCount = $this->countQueries(function () use ($small): void {
            $this->actingAs($small)->get('/invoices')->assertSuccessful();
        });

        $largeCount = $this->countQueries(function () use ($large): void {
            $this->actingAs($large)->get('/invoices')->assertSuccessful();
        });

        $this->assertLessThanOrEqual(
            $smallCount + self::QUERY_JITTER,
            $largeCount,
            "PERF-CI-2: the web invoice index must be O(1) in row count — saw {$smallCount} queries for 5 invoices vs {$largeCount} for 30. ".
            'A 6x row increase adding more than '.self::QUERY_JITTER.' queries is an N+1.',
        );
    }

    public function test_web_tenant_index_query_count_is_constant_under_n(): void
    {
        $small = $this->seedLandlordWithTenants(2);
        $large = $this->seedLandlordWithTenants(6);

        $smallCount = $this->countQueries(function () use ($small): void {
            $this->actingAs($small)->get('/tenants')->assertSuccessful();
        });

        $largeCount = $this->countQueries(function () use ($large): void {
            $this->actingAs($large)->get('/tenants')->assertSuccessful();
        });

        $this->assertLessThanOrEqual(
            $smallCount + self::QUERY_JITTER,
            $largeCount,
            "PERF-CI-2: the web tenant index must be O(1) in tenant count — saw {$smallCount} queries for 2 tenants vs {$largeCount} for 6.",
        );
    }
}
