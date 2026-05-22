<?php

declare(strict_types=1);

namespace Tests\Feature\TenantPortal;

use App\Services\Tenant\TenantPaymentMethodService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase-84 CI: consolidated TENANT-PORTAL-DEPTH surface watchdog.
 */
class Phase84TenantPortalSurfaceTest extends TestCase
{
    public function test_routes_registered(): void
    {
        foreach ([
            'tenant.payment-methods.index',
            'tenant.payment-methods.store',
            'tenant.payment-methods.default',
            'tenant.payment-methods.destroy',
            'tenant.renewals.index',
            'tenant.invoices.download',
        ] as $name) {
            $this->assertNotNull(Route::getRoutes()->getByName($name), "Missing route: {$name}");
        }
    }

    public function test_service_bound(): void
    {
        $this->assertInstanceOf(TenantPaymentMethodService::class, app(TenantPaymentMethodService::class));
    }

    public function test_pages_exist(): void
    {
        $this->assertFileExists(resource_path('js/Pages/Tenant/PaymentMethods.vue'));
        $this->assertFileExists(resource_path('js/Pages/Tenant/Renewals.vue'));
    }

    public function test_renewal_index_method_exists(): void
    {
        $this->assertTrue(method_exists(\App\Http\Controllers\Tenant\RenewalResponseController::class, 'index'));
        $this->assertTrue(method_exists(\App\Http\Controllers\TenantFinancesController::class, 'downloadInvoice'));
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

        foreach (['tenant_payment_method', 'tenant_renewal', 'tenant_finances'] as $namespace) {
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
        $this->assertFileExists(base_path('docs/runbooks/tenant-portal.md'));
    }
}
