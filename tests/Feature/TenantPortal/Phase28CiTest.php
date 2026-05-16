<?php

declare(strict_types=1);

namespace Tests\Feature\TenantPortal;

use App\Models\User;
use App\Support\TenantAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-28 TENANT-CI-2/3 watchdog suite.
 *
 * Enforces parity between TenantAbilities::ABILITY_KEYS, the keys
 * returned by TenantAbilities::for() at runtime, and the contract
 * table in docs/runbooks/tenant-portal.md.
 */
class Phase28CiTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_tenant_abilities_runtime_keys_match_constant(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first(),
        );

        $abilities = TenantAbilities::for($tenant);
        sort($abilities);

        $expected = TenantAbilities::ABILITY_KEYS;
        $actual = array_keys(TenantAbilities::for($tenant));

        sort($expected);
        sort($actual);

        $this->assertSame(
            $expected,
            $actual,
            'TenantAbilities::for() return keys diverged from ABILITY_KEYS constant — keep them in sync.',
        );
    }

    public function test_tenant_abilities_shared_to_inertia_for_tenant_only(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first(),
        );

        $tenantResp = $this->actingAs($tenant)->get(route('dashboard'));
        $tenantResp->assertInertia(fn ($page) => $page
            ->has('auth.tenant_abilities')
            ->where('auth.tenant_abilities.statement:download', true)
            ->where('auth.tenant_abilities.tickets:create', true)
        );

        $landlordResp = $this->actingAs($setup['landlord'])->get(route('dashboard'));
        $landlordResp->assertInertia(fn ($page) => $page
            ->where('auth.tenant_abilities', null)
        );
    }

    public function test_runbook_documents_every_ability_key(): void
    {
        $runbook = file_get_contents(base_path('docs/runbooks/tenant-portal.md'));
        $this->assertIsString($runbook, 'docs/runbooks/tenant-portal.md must exist');

        foreach (TenantAbilities::ABILITY_KEYS as $key) {
            $this->assertStringContainsString(
                "`{$key}`",
                $runbook,
                "Ability key '{$key}' is exposed by TenantAbilities but is not documented in docs/runbooks/tenant-portal.md ability matrix.",
            );
        }
    }

    public function test_runbook_lists_every_phase28_test_class(): void
    {
        $runbook = file_get_contents(base_path('docs/runbooks/tenant-portal.md'));
        $classes = [
            'Phase28ProfileTest',
            'Phase28StatementTest',
            'Phase28DocsTest',
            'Phase28MaintTest',
            'Phase28PayTest',
            'Phase28TenantSurfaceTest',
            'Phase28CiTest',
        ];

        foreach ($classes as $class) {
            $this->assertStringContainsString(
                $class,
                $runbook,
                "Watchdog '{$class}' is shipped but not referenced in docs/runbooks/tenant-portal.md CI gates section.",
            );
        }
    }
}
