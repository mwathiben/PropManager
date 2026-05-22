<?php

declare(strict_types=1);

namespace Tests\Feature\Lease;

use App\Services\Documents\DocumentGenerationService;
use App\Services\Lease\LeaseLifecycleService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-83 CI: consolidated LEASE-DEPTH surface watchdog.
 */
class Phase83LeaseDepthSurfaceTest extends TestCase
{
    public function test_schema_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('rent_escalations'));
        $this->assertTrue(Schema::hasTable('lease_co_tenants'));
        $this->assertTrue(Schema::hasTable('lease_guarantors'));
        $this->assertTrue(Schema::hasColumns('rent_escalations', [
            'escalation_type', 'amount', 'effective_date', 'status', 'new_rent_amount', 'rent_history_id',
        ]));
    }

    public function test_services_bound(): void
    {
        $this->assertInstanceOf(LeaseLifecycleService::class, app(LeaseLifecycleService::class));
        $this->assertTrue(method_exists(DocumentGenerationService::class, 'generateLeaseAgreement'));
        $this->assertTrue(method_exists(DocumentGenerationService::class, 'generateRenewalOffer'));
    }

    public function test_routes_registered(): void
    {
        foreach ([
            'rent-escalations.store',
            'rent-escalations.destroy',
            'lease-co-tenants.store',
            'lease-co-tenants.destroy',
            'lease-guarantors.store',
            'lease-guarantors.release',
            'documents.generate-lease',
            'documents.generate-renewal-offer',
            'leases.show',
        ] as $name) {
            $this->assertNotNull(Route::getRoutes()->getByName($name), "Missing route: {$name}");
        }
    }

    public function test_commands_exit_zero(): void
    {
        $this->artisan('rent:apply-escalations')->assertExitCode(0);
        $this->artisan('rent:escalation-rollup')->assertExitCode(0);
    }

    public function test_lease_show_renders_inertia_component(): void
    {
        $contents = file_get_contents(base_path('resources/js/Pages/Leases/Show.vue'));
        $this->assertStringContainsString('rent-escalations.store', $contents);
        $this->assertStringContainsString('lease-guarantors.store', $contents);
    }

    public function test_lang_parity(): void
    {
        $flatten = function (array $a, string $prefix = '') use (&$flatten): array {
            $keys = [];
            foreach ($a as $k => $v) {
                $keys = is_array($v) ? [...$keys, ...$flatten($v, "{$prefix}{$k}.")] : [...$keys, "{$prefix}{$k}"];
            }

            return $keys;
        };

        foreach (['lease', 'lease_doc'] as $namespace) {
            $en = $flatten(require base_path("lang/en/{$namespace}.php"));
            $sw = $flatten(require base_path("lang/sw/{$namespace}.php"));
            $ar = $flatten(require base_path("lang/ar/{$namespace}.php"));
            sort($en);
            sort($sw);
            sort($ar);
            $this->assertSame($en, $sw, "sw/{$namespace}.php key drift");
            $this->assertSame($en, $ar, "ar/{$namespace}.php key drift");
        }
    }

    public function test_runbook_exists(): void
    {
        $this->assertFileExists(base_path('docs/runbooks/lease.md'));
    }
}
