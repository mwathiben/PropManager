<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\AuthAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-21 DEFER-AUTHZ-1 + DEFER-AUTHZ-2: broader v-if=can() adoption +
 * CI watchdog asserting the gating floor doesn't regress.
 *
 * Pre-Phase-21: only AuthenticatedLayout's admin nav was gated via can()
 * (Phase 20 canonical seed). Phase 21 broadens adoption to 22+ Vue files
 * covering destructive/admin action buttons (impersonate, delete tenant
 * invitation, delete document, etc.). The watchdog below counts sites +
 * fails CI if the count regresses — a future PR removing a v-if=can()
 * without replacing it is the regression we're catching.
 */
class Phase21FrontAuthzTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Phase-21 DEFER-AUTHZ-1: management abilities surfaced in the
     * abilities map. Adding a new management ability requires updating
     * BOTH this test AND useAuth.ts AND AuthAbilities::for().
     */
    public function test_auth_abilities_for_landlord_includes_phase21_management_gates(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $abilities = AuthAbilities::for($landlord);

        $managementGates = [
            'tenants:manage' => true,
            'invoices:manage' => true,
            'payments:manage' => true,
            'properties:manage' => true,
            'buildings:manage' => true,
            'units:manage' => true,
            'documents:manage' => true,
            'settings:manage' => true,
            'team:manage' => true,
            'templates:manage' => true,
            'finances:manage' => true,
            'imports:manage' => true,
        ];

        foreach ($managementGates as $ability => $expected) {
            $this->assertArrayHasKey(
                $ability,
                $abilities,
                "Phase-21 DEFER-AUTHZ-1: AuthAbilities::for() must expose '$ability' to the Vue frontend.",
            );
            $this->assertSame(
                $expected,
                $abilities[$ability],
                "Landlord must have $ability = $expected.",
            );
        }
    }

    public function test_caretaker_has_limited_management_subset(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $landlord->id,
        ]);
        $abilities = AuthAbilities::for($caretaker);

        $this->assertTrue($abilities['tenants:manage'], 'Caretaker manages tenants.');
        $this->assertTrue($abilities['invoices:manage'], 'Caretaker manages invoices.');
        $this->assertTrue($abilities['documents:manage'], 'Caretaker manages documents.');
        $this->assertTrue($abilities['payments:manage'], 'Caretaker manages payments.');

        $this->assertFalse($abilities['properties:manage'], 'Caretaker cannot manage properties (landlord-only).');
        $this->assertFalse($abilities['settings:manage'], 'Caretaker cannot manage settings.');
        $this->assertFalse($abilities['team:manage'], 'Caretaker cannot manage team.');
        $this->assertFalse($abilities['finances:manage'], 'Caretaker cannot manage finances.');
    }

    public function test_tenant_has_no_management_abilities(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
        ]);
        $abilities = AuthAbilities::for($tenant);

        $managementGates = [
            'tenants:manage', 'invoices:manage', 'payments:manage',
            'properties:manage', 'buildings:manage', 'units:manage',
            'documents:manage', 'settings:manage', 'team:manage',
            'templates:manage', 'finances:manage', 'imports:manage',
        ];

        foreach ($managementGates as $ability) {
            $this->assertFalse($abilities[$ability], "Tenant must NOT have $ability.");
        }
    }

    public function test_dpa_restricted_landlord_loses_management_abilities(): void
    {
        // Phase-13 DPA-4: a restricted landlord must lose all write-side
        // abilities including the Phase-21 management Gates. The
        // Gate::before hook in AuthServiceProvider denies any ability
        // not on the read-side allow-list.
        $restricted = User::factory()->create([
            'role' => 'landlord',
            'restricted_at' => now(),
        ]);
        $abilities = AuthAbilities::for($restricted);

        $managementGates = [
            'tenants:manage', 'invoices:manage', 'properties:manage',
            'settings:manage', 'finances:manage',
        ];

        foreach ($managementGates as $ability) {
            $this->assertFalse(
                $abilities[$ability],
                "DPA-4 restricted landlord must NOT pass $ability (write-side ability).",
            );
        }
    }

    /**
     * Phase-21 DEFER-AUTHZ-2: CI watchdog. Scans resources/js/Pages for
     * action button patterns gated by v-if=can('...'). Asserts at least
     * BASELINE_COUNT sites remain — a future PR removing a gate
     * without replacement fails CI immediately.
     *
     * To raise the floor after adding more gates, increment the constant
     * BELOW + add the new files to the count comment. Lowering the
     * baseline requires a corresponding PR explanation (why a gate was
     * intentionally removed — typically because the button itself was
     * deleted).
     */
    public function test_phase21_authz_seeded_count_watchdog(): void
    {
        // Baseline: 26 v-if=can('...') occurrences across 22 files
        // as of phase21-1d commit. Lowering this is a regression unless
        // the action button was intentionally deleted.
        $baseline = 25;

        $pagesPath = base_path('resources/js/Pages');
        $files = $this->collectVueFiles($pagesPath);
        $count = 0;
        $matchedFiles = [];

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            // Match v-if="can('ability-name')" or v-if="can('ability') && ..."
            $matches = preg_match_all('/v-if="[^"]*can\(\s*[\'"]([a-z][a-z\-:]*)[\'"]\s*\)/i', $contents);
            if ($matches > 0) {
                $count += $matches;
                $matchedFiles[] = str_replace($pagesPath.DIRECTORY_SEPARATOR, '', $file);
            }
        }

        $this->assertGreaterThanOrEqual(
            $baseline,
            $count,
            "Phase-21 DEFER-AUTHZ-2 watchdog: v-if=can('...') count regressed from baseline $baseline. ".
            "Found $count occurrences across ".count($matchedFiles).' files. '.
            'A PR removing a gate without replacement requires an explanation in the commit body.',
        );
    }

    /**
     * Companion watchdog: ensure every Phase-21 management Gate is
     * actually CONSUMED somewhere in resources/js/Pages. Defining a
     * Gate without consuming it creates a misleading impression of
     * authz comprehensiveness — same anti-pattern Phase-18 AUTHZ-1
     * cleaned up server-side.
     */
    public function test_phase21_management_gates_have_consumer_call_sites(): void
    {
        $pagesPath = base_path('resources/js/Pages');
        $files = $this->collectVueFiles($pagesPath);
        $allSources = '';
        foreach ($files as $file) {
            $allSources .= file_get_contents($file)."\n";
        }

        $managementGates = [
            'tenants:manage',
            'invoices:manage',
            'settings:manage',
            'team:manage',
            'templates:manage',
            'finances:manage',
            'imports:manage',
            'documents:manage',
        ];

        foreach ($managementGates as $ability) {
            $this->assertStringContainsString(
                "can('".$ability."')",
                $allSources,
                "Phase-21 DEFER-AUTHZ-1: management Gate '$ability' has no consumer call site in resources/js/Pages. ".
                'Defining a Gate without a UI consumer creates a misleading authorization impression. '.
                'Either consume it via v-if=can(...) or remove the Gate definition.',
            );
        }
    }

    /**
     * Phase-21 DEFER-AUTHZ-4: a real server-side 403 on an HTML request
     * must render the dedicated Errors/403 Inertia page (status 403),
     * not the raw Symfony error overlay.
     */
    public function test_403_returns_inertia_page_for_html_requests(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($landlord)->get('/admin/users');

        $response->assertStatus(403);
        $response->assertInertia(fn ($page) => $page->component('Errors/403'));
    }

    /**
     * Phase-21 DEFER-AUTHZ-4: an unknown URL on an HTML request must
     * render the dedicated Errors/404 Inertia page (status 404).
     */
    public function test_404_returns_inertia_page_for_html_requests(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($user)->get('/this-route-does-not-exist-'.uniqid());

        $response->assertStatus(404);
        $response->assertInertia(fn ($page) => $page->component('Errors/404'));
    }

    /**
     * Phase-21 DEFER-AUTHZ-4: API 403s keep their JSON shape — the
     * Inertia error-page render only applies to HTML requests.
     */
    public function test_api_403_stays_json(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($landlord)
            ->getJson('/admin/users');

        $response->assertStatus(403);
        // A JSON 403 must NOT carry the Inertia HTML page payload.
        $this->assertStringNotContainsString('Errors/403', $response->getContent());
    }

    /**
     * Phase-21 DEFER-AUTHZ-4: the /403 landing route (target of the
     * client-side router guard redirect) renders the Errors/403 page.
     */
    public function test_client_guard_403_landing_route_renders_error_page(): void
    {
        $response = $this->get('/403');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Errors/403'));
    }

    /**
     * Phase-21 DEFER-AUTHZ-4: source-level watchdog for the client-side
     * route guard. app.js must register the Inertia `before` hook and
     * carry the URL-prefix → ability table; regressing either silently
     * disables client-side pre-checking.
     */
    public function test_app_js_registers_client_side_route_guard(): void
    {
        $appJs = file_get_contents(base_path('resources/js/app.js'));

        $this->assertStringContainsString(
            "router.on('before'",
            $appJs,
            'DEFER-AUTHZ-4: app.js must register the Inertia `before` hook for client-side route guarding.',
        );
        $this->assertStringContainsString(
            'ROUTE_ABILITY_MAP',
            $appJs,
            'DEFER-AUTHZ-4: app.js must define the URL-prefix → required-ability table.',
        );
        $this->assertStringContainsString(
            "router.visit('/403')",
            $appJs,
            'DEFER-AUTHZ-4: the guard must redirect to /403 when the abilities map lacks the required ability.',
        );
    }

    public function test_error_pages_exist(): void
    {
        $this->assertFileExists(
            base_path('resources/js/Pages/Errors/403.vue'),
            'DEFER-AUTHZ-4: Errors/403.vue page component must exist.',
        );
        $this->assertFileExists(
            base_path('resources/js/Pages/Errors/404.vue'),
            'DEFER-AUTHZ-4: Errors/404.vue page component must exist.',
        );
    }

    /**
     * @return array<int, string>
     */
    private function collectVueFiles(string $path): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'vue') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
