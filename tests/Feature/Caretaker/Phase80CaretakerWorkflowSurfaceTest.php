<?php

declare(strict_types=1);

namespace Tests\Feature\Caretaker;

use App\Services\Maintenance\CaretakerPerformanceService;
use App\Services\Maintenance\TicketEscalationService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-80 CI: consolidated CARETAKER-WORKFLOW-DEEP surface watchdog.
 */
class Phase80CaretakerWorkflowSurfaceTest extends TestCase
{
    public function test_escalation_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('tickets', [
            'escalated_at', 'escalated_by', 'escalation_reason',
            'escalation_acknowledged_at', 'escalation_acknowledged_by',
        ]));
    }

    public function test_services_are_bound(): void
    {
        $this->assertInstanceOf(TicketEscalationService::class, app(TicketEscalationService::class));
        $this->assertInstanceOf(CaretakerPerformanceService::class, app(CaretakerPerformanceService::class));
    }

    public function test_routes_are_registered(): void
    {
        foreach ([
            'tasks.index', 'tasks.transition', 'tasks.escalate',
            'tickets.escalation.acknowledge', 'maintenance.caretaker-performance',
        ] as $name) {
            $this->assertNotNull(Route::getRoutes()->getByName($name), "Missing route: {$name}");
        }
    }

    public function test_task_routes_are_caretaker_gated_and_perf_landlord_gated(): void
    {
        $this->assertContains('role:caretaker', Route::getRoutes()->getByName('tasks.index')->gatherMiddleware());
        $this->assertContains('role:caretaker', Route::getRoutes()->getByName('tasks.escalate')->gatherMiddleware());
        $this->assertContains('role:landlord', Route::getRoutes()->getByName('maintenance.caretaker-performance')->gatherMiddleware());
        $this->assertContains('role:landlord', Route::getRoutes()->getByName('tickets.escalation.acknowledge')->gatherMiddleware());
    }

    public function test_rollup_command_exits_zero(): void
    {
        $this->artisan('caretaker:performance-rollup')->assertExitCode(0);
    }

    public function test_pages_exist(): void
    {
        $this->assertFileExists(resource_path('js/Pages/Caretaker/TaskBoard.vue'));
        $this->assertFileExists(resource_path('js/Pages/Maintenance/CaretakerPerformance.vue'));
    }

    public function test_runbook_exists(): void
    {
        $this->assertFileExists(base_path('docs/runbooks/caretaker.md'));
    }

    public function test_maintenance_lang_parity_with_escalation_and_perf(): void
    {
        $flatten = function (array $a, string $prefix = '') use (&$flatten): array {
            $keys = [];
            foreach ($a as $k => $v) {
                $keys = is_array($v) ? [...$keys, ...$flatten($v, "{$prefix}{$k}.")] : [...$keys, "{$prefix}{$k}"];
            }

            return $keys;
        };

        $en = $flatten(require base_path('lang/en/maintenance.php'));
        $sw = $flatten(require base_path('lang/sw/maintenance.php'));
        $ar = $flatten(require base_path('lang/ar/maintenance.php'));
        sort($en);
        sort($sw);
        sort($ar);

        $this->assertSame($en, $sw, 'sw/maintenance.php key drift');
        $this->assertSame($en, $ar, 'ar/maintenance.php key drift');
        $this->assertContains('escalation.raised', $en);
        $this->assertContains('task_board.title', $en);
        $this->assertContains('caretaker_perf.title', $en);
    }
}
