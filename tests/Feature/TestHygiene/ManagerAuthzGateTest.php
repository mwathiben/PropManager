<?php

declare(strict_types=1);

namespace Tests\Feature\TestHygiene;

use Tests\TestCase;

/**
 * MANAGER-AUTHZ-1 guardrail. A `manager` is a full scope owner (landlord_id ==
 * its own id), so wherever a landlord is authorized — or its scope id resolved —
 * a manager must be treated identically. This is a deterministic watchdog that
 * fails CI the moment a manager-excluding pattern reappears, so the systemic gap
 * that spanned ~60 FormRequests + 40 Policies + services/observers/channels
 * cannot regress.
 *
 * Two patterns are banned in scope-owner code:
 *  - Authorization GATES on isLandlord() (FormRequests, Policies, Controllers,
 *    Providers' Gate::define resolvers, API Resource field-visibility checks)
 *    — silently 403 the manager role or hide its in-scope data.
 *  - Scope RESOLUTION by isLandlord() / role === 'landlord' (controllers,
 *    services, observers, broadcast channels) — must use isScopeOwner() /
 *    effectiveScopeId() so a manager resolves to its own scope rather than
 *    being mis-scoped or denied.
 *
 * The ONLY escape hatch is INTENTIONALLY_LANDLORD_ONLY, which requires a
 * concrete justification and is reviewed.
 */
class ManagerAuthzGateTest extends TestCase
{
    /**
     * Files (relative to their scanned dir) where the landlord-only check is
     * intentional and a manager must NOT pass. Keep empty unless there is a real
     * reason a scope-owner manager is forbidden.
     *
     * @var list<string>
     */
    private const INTENTIONALLY_LANDLORD_ONLY = [
        // /register only ever mints landlord/caretaker/tenant — managers are
        // provisioned through the admin flow, never self-registration — and the
        // role check guards LandlordWelcome, which is landlord-specific
        // onboarding copy. A manager reaching it must NOT receive that mail.
        'Auth/RegisteredUserController.php',

        // AuthServiceProvider defines the `manage-subscription` Gate as
        // isLandlord()-only BY DESIGN (Phase-19 POLICY-5): a manager runs
        // properties on the owners' behalf but does NOT own the platform
        // subscription/billing relationship — only the account-owner landlord
        // may manage it. EVERY other gate in this provider (the $manageGates
        // set + view-audit-logs + viewApiDocs) admits managers via
        // isScopeOwner(); this single landlord-only gate is pinned by
        // ManagerProviderGatesTest::test_manager_is_denied_manage_subscription_gate.
        'AuthServiceProvider.php',
    ];

    public function test_no_controller_gates_on_landlord_role(): void
    {
        $this->assertNoLandlordOnlyIn(app_path('Http/Controllers'), 'Controller');
    }

    public function test_no_form_request_gates_on_landlord_role(): void
    {
        $this->assertNoLandlordOnlyIn(app_path('Http/Requests'), 'FormRequest');
    }

    public function test_no_policy_gates_on_landlord_role(): void
    {
        $this->assertNoLandlordOnlyIn(app_path('Policies'), 'Policy');
    }

    public function test_no_service_resolves_scope_by_landlord_role(): void
    {
        $this->assertNoLandlordOnlyIn(app_path('Services'), 'Service');
    }

    public function test_no_observer_resolves_scope_by_landlord_role(): void
    {
        $this->assertNoLandlordOnlyIn(app_path('Observers'), 'Observer');
    }

    public function test_no_broadcast_channel_resolves_scope_by_landlord_role(): void
    {
        $this->assertNoLandlordOnlyIn(app_path('Broadcasting'), 'Broadcast channel');
    }

    public function test_no_provider_gates_on_landlord_role(): void
    {
        // Cross-cutting Gate::define() resolvers live here (AuthServiceProvider).
        // A manager is a scope owner and must pass every scope-owner ability —
        // the only landlord-only exception (manage-subscription) is hatched in
        // INTENTIONALLY_LANDLORD_ONLY with justification.
        $this->assertNoLandlordOnlyIn(app_path('Providers'), 'Provider');
    }

    public function test_no_resource_gates_on_landlord_role(): void
    {
        // API Resources gate PII/field visibility by role (e.g. TenantResource).
        // A manager must see tenant data in its own scope, same as a landlord.
        $this->assertNoLandlordOnlyIn(app_path('Http/Resources'), 'API Resource');
    }

    private function assertNoLandlordOnlyIn(string $dir, string $kind): void
    {
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace([$dir.DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], ['', '/'], $file->getPathname());

            if (in_array($relative, self::INTENTIONALLY_LANDLORD_ONLY, true)) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (str_contains($contents, 'isLandlord(') || preg_match("/role\\s*===?\\s*'landlord'/", $contents) === 1) {
                $offenders[] = $relative;
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "{$kind} authorization/scope-resolution must use isScopeOwner(), not isLandlord() or role === 'landlord' — these lock out or mis-scope the manager role:\n  - ".implode("\n  - ", $offenders),
        );
    }
}
