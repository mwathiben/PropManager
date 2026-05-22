<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Models\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-85 CI: consolidated PAYMENTS-GATEWAY-DEPTH surface watchdog.
 */
class Phase85PaymentsGatewayDepthSurfaceTest extends TestCase
{
    public function test_schema(): void
    {
        $this->assertTrue(Schema::hasTable('payment_disputes'));
        $this->assertTrue(Schema::hasColumns('refunds', ['retry_count', 'needs_review']));
        $this->assertTrue(Schema::hasColumn('notification_preferences', 'payment_dispute_enabled'));
    }

    public function test_routes_registered(): void
    {
        foreach (['gateway-reconciliation.index', 'gateway-reconciliation.show'] as $name) {
            $this->assertNotNull(Route::getRoutes()->getByName($name), "Missing route: {$name}");
        }
    }

    public function test_commands_exit_zero(): void
    {
        $this->artisan('refunds:retry-failed')->assertExitCode(0);
        $this->artisan('payments:reconciliation-rollup')->assertExitCode(0);
    }

    public function test_dispute_notification_type_mapped(): void
    {
        $this->assertArrayHasKey(Notification::TYPE_PAYMENT_DISPUTE, Notification::TYPE_URGENCY_MAP);
    }

    public function test_refund_service_has_retry(): void
    {
        $this->assertTrue(method_exists(\App\Services\RefundService::class, 'retry'));
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

        foreach (['gateway_reconciliation', 'payment_dispute', 'refund'] as $namespace) {
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
        $this->assertFileExists(base_path('docs/runbooks/payments-gateway.md'));
    }
}
