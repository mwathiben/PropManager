<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Services\Finance\DepositSettlementService;
use App\Services\Finance\PeriodCloseReadinessService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-81 CI: consolidated FINANCE-DEPTH surface watchdog.
 */
class Phase81FinanceDepthSurfaceTest extends TestCase
{
    public function test_services_are_bound(): void
    {
        $this->assertInstanceOf(DepositSettlementService::class, app(DepositSettlementService::class));
        $this->assertInstanceOf(PeriodCloseReadinessService::class, app(PeriodCloseReadinessService::class));
    }

    public function test_routes_registered(): void
    {
        foreach ([
            'finances.reconciliation.import',
            'finances.reconciliation.process-queue',
            'finances.periods.close',
            'finances.periods.reopen',
            'finances.late-fees.apply-now',
        ] as $name) {
            $this->assertNotNull(Route::getRoutes()->getByName($name), "Missing route: {$name}");
        }
    }

    public function test_reopen_audit_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('accounting_periods', ['reopened_at', 'reopened_by_user_id', 'reopen_reason']));
    }

    public function test_backfill_command_registered(): void
    {
        $this->artisan('deposits:backfill-received')->assertExitCode(0);
    }

    public function test_import_endpoint_is_not_a_stub(): void
    {
        // The controller method must reference the real import class, not a
        // "coming soon" stub.
        $src = file_get_contents(app_path('Http/Controllers/FinancesController.php'));
        $this->assertStringContainsString('BankStatementImport', $src);
        $this->assertStringNotContainsString('coming soon', $src);
    }

    public function test_finance_lang_parity(): void
    {
        $flatten = function (array $a, string $prefix = '') use (&$flatten): array {
            $keys = [];
            foreach ($a as $k => $v) {
                $keys = is_array($v) ? [...$keys, ...$flatten($v, "{$prefix}{$k}.")] : [...$keys, "{$prefix}{$k}"];
            }

            return $keys;
        };

        $en = $flatten(require base_path('lang/en/finance.php'));
        $sw = $flatten(require base_path('lang/sw/finance.php'));
        $ar = $flatten(require base_path('lang/ar/finance.php'));
        sort($en);
        sort($sw);
        sort($ar);

        $this->assertSame($en, $sw, 'sw/finance.php key drift');
        $this->assertSame($en, $ar, 'ar/finance.php key drift');
    }

    public function test_runbook_exists(): void
    {
        $this->assertFileExists(base_path('docs/runbooks/finance.md'));
    }
}
