<?php

declare(strict_types=1);

namespace Tests\Feature\Smoke;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Server-side health sweep for the role-specific route groups that a
 * landlord-session sweep can never reach (it gets 403 before the controller
 * even runs). Each group is hit GET-only as a representative user of that
 * role; anything that returns a 5xx is a real controller crash and a bug.
 *
 * Only >=500 is treated as failure. 200/302/403/404 are all legitimate
 * outcomes (empty state, redirect to a setup step, authorization, optional
 * feature) and must not fail the sweep.
 */
class MultiRoleRouteSmokeTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    /** @return list<string> */
    private function sweep(User $actor, array $routes): array
    {
        $failures = [];
        foreach ($routes as $route) {
            $response = $this->actingAs($actor)->get($route);
            $status = $response->getStatusCode();
            if ($status >= 500) {
                $message = $response->exception?->getMessage() ?? '(no exception captured)';
                $failures[] = "{$route} -> {$status}: {$message}";
            }
        }

        return $failures;
    }

    public function test_super_admin_admin_and_ops_routes_do_not_500(): void
    {
        $this->createLandlordWithFullSetup();
        $admin = User::factory()->create(['role' => 'super_admin']);

        $failures = $this->sweep($admin, [
            '/admin/billing',
            '/admin/billing/analytics',
            '/admin/billing/history',
            '/admin/gateways',
            '/admin/landlords',
            '/admin/settings',
            '/admin/users',
            '/operations',
            '/ops',
            '/ops/experiments',
            '/ops/incidents',
            '/ops/mrr',
            '/ops/landlord-cost',
            '/ops/onboarding/funnel',
            '/ops/push',
            '/ops/growth/attribution',
            '/ops/growth/cohort-retention',
            '/ops/growth/referral-leaderboard',
        ]);

        $this->assertSame([], $failures, "Super-admin routes returning 5xx:\n".implode("\n", $failures));
    }

    public function test_owner_portal_routes_do_not_500(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();
        $owner = User::factory()->create(['role' => 'owner', 'landlord_id' => $landlord->id]);

        $failures = $this->sweep($owner, [
            '/owner-portal',
            '/owner-portal/notifications',
            '/owner-portal/payouts',
            '/owner-portal/statements',
        ]);

        $this->assertSame([], $failures, "Owner-portal routes returning 5xx:\n".implode("\n", $failures));
    }

    public function test_tenant_portal_routes_do_not_500(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant] = $this->createTenantWithActiveLease($landlord, $units->first());

        $failures = $this->sweep($tenant, [
            '/tenant/lease',
            '/tenant/finances',
            '/tenant/finances/history',
            '/tenant/inbox',
            '/tenant/notifications',
            '/tenant/payments',
            '/tenant/payment-methods',
            '/tenant/profile',
            '/tenant/renewals',
            '/tenant/statement',
            '/tenant/wallet',
            '/tenant/water',
            '/tenant/documents',
        ]);

        $this->assertSame([], $failures, "Tenant-portal routes returning 5xx:\n".implode("\n", $failures));
    }

    public function test_caretaker_routes_do_not_500(): void
    {
        ['landlord' => $landlord, 'building' => $building] = $this->createLandlordWithFullSetup();
        $caretaker = $this->createCaretakerForLandlord($landlord, $building);

        $failures = $this->sweep($caretaker, [
            '/maintenance',
            '/maintenance/caretaker-performance',
            '/maintenance/photos',
            '/maintenance/vendor-performance',
            '/my-tasks',
            '/readings',
            '/readings/history',
        ]);

        $this->assertSame([], $failures, "Caretaker routes returning 5xx:\n".implode("\n", $failures));
    }
}
