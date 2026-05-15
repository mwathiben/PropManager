<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Phase-25 API-DOC-1 watchdog: the OpenAPI 3.1 spec is served by
 * dedoc/scramble at /docs/api.json, generated from FormRequests +
 * JsonResources + route definitions. The spec is the integrator's
 * canonical contract.
 */
class Phase25DocTest extends TestCase
{
    use RefreshDatabase;

    public function test_scramble_package_is_a_dependency(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        $this->assertArrayHasKey(
            'dedoc/scramble',
            $composer['require'] ?? [],
            'API-DOC-1: dedoc/scramble must be a runtime dependency for OpenAPI spec generation.',
        );
    }

    public function test_scramble_routes_are_registered(): void
    {
        // Scramble auto-registers two routes: GET /docs/api (UI) and
        // GET /docs/api.json (spec). Confirm both are reachable through
        // Laravel's route table.
        $routes = collect(app('router')->getRoutes()->getRoutes())->map(
            fn ($r) => $r->methods()[0].' '.$r->uri(),
        );

        $this->assertTrue(
            $routes->contains('GET docs/api'),
            'API-DOC-1: Scramble must register the docs UI at /docs/api.',
        );
        $this->assertTrue(
            $routes->contains('GET docs/api.json'),
            'API-DOC-1: Scramble must register the OpenAPI spec at /docs/api.json.',
        );
    }

    public function test_scramble_config_targets_the_api_path(): void
    {
        $this->assertSame(
            'api',
            config('scramble.api_path'),
            'API-DOC-1: scramble.api_path must be "api" so the spec covers /api/* routes.',
        );
    }

    public function test_export_command_produces_valid_3_1_spec(): void
    {
        // The CI build will run `php artisan scramble:export` to produce
        // the spec artifact; reviewers diff it against the merge-base.
        // This test simulates the artifact's content via the Scramble
        // generator service so we don't depend on disk state.
        $generator = app(\Dedoc\Scramble\Generator::class);
        $spec = $generator();

        $this->assertIsArray($spec);
        $this->assertArrayHasKey('openapi', $spec, 'API-DOC-1: spec must declare openapi version.');
        $this->assertStringStartsWith(
            '3.1.',
            $spec['openapi'],
            'API-DOC-1: spec must be OpenAPI 3.1.x.',
        );
        $this->assertArrayHasKey('paths', $spec);
        $this->assertGreaterThan(
            10,
            count($spec['paths']),
            'API-DOC-1: spec must document the API surface (expected >10 paths, got '.count($spec['paths']).').',
        );

        // Spot-check that the v1 auth + tenant endpoints (the
        // best-covered surface today) are in the spec.
        $this->assertArrayHasKey('/v1/auth/login', $spec['paths']);
        $this->assertArrayHasKey('/v1/tenant/lease', $spec['paths']);
    }

    public function test_view_api_docs_gate_is_registered(): void
    {
        // API-DOC-2: Scramble's RestrictedDocsAccess middleware calls
        // Gate::allows('viewApiDocs'). Without a Gate registration the
        // call returns false and every non-local request to /docs/api
        // would 403. Confirm the gate exists.
        $this->assertTrue(
            Gate::has('viewApiDocs'),
            'API-DOC-2: a Gate named "viewApiDocs" must be registered so Scramble can gate /docs/api.',
        );
    }

    public function test_view_api_docs_gate_grants_landlords_and_super_admins(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->assertTrue(
            Gate::forUser($landlord)->allows('viewApiDocs'),
            'API-DOC-2: landlords must be able to view /docs/api.',
        );

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->assertTrue(
            Gate::forUser($admin)->allows('viewApiDocs'),
            'API-DOC-2: super-admins must be able to view /docs/api.',
        );
    }

    public function test_view_api_docs_gate_denies_tenants_and_caretakers(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $this->assertFalse(
            Gate::forUser($tenant)->allows('viewApiDocs'),
            'API-DOC-2: tenants must NOT see /docs/api — the docs surface server routes that should not be browsable.',
        );

        $caretaker = User::factory()->create(['role' => 'caretaker']);
        $this->assertFalse(
            Gate::forUser($caretaker)->allows('viewApiDocs'),
            'API-DOC-2: caretakers do not need API docs and should not see them either.',
        );
    }

    public function test_scramble_middleware_includes_restricted_docs_access(): void
    {
        // API-DOC-2: the Gate is only enforced if RestrictedDocsAccess is
        // actually wired into Scramble's middleware stack. If somebody
        // removes it from config/scramble.php, the gate becomes dead
        // code — this watchdog catches that.
        $this->assertContains(
            \Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess::class,
            config('scramble.middleware'),
            'API-DOC-2: config/scramble.php middleware stack must include RestrictedDocsAccess.',
        );
    }
}
