<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\DegradationDetected;
use App\Services\MetricsService;
use App\Services\Sre\AlertFiringRecorder;
use App\Services\Sre\DependencyHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-32 SRE-DEPS-2: every 5 minutes poll every supported upstream
 * via DependencyHealthService. Emits per-dep gauges + fires
 * DegradationDetected on transitions and the dependency_down alert
 * when any dep is down.
 *
 *   - dependency_up{dep=X} : 1.0 up, 0.5 degraded, 0.0 down
 *   - dependency_latency_ms{dep=X}
 */
class OutboundHealthCheck extends Command
{
    protected $signature = 'outbound:health-check {--dep=}';

    protected $description = 'Phase-32 SRE-DEPS-2: poll every upstream dependency + emit health gauges.';

    public function handle(
        DependencyHealthService $checker,
        MetricsService $metrics,
        AlertFiringRecorder $recorder,
    ): int {
        $only = $this->option('dep');
        $deps = $only !== null ? [$only] : DependencyHealthService::SUPPORTED;

        $downAny = false;
        foreach ($deps as $dep) {
            $result = $checker->check($dep);
            $status = (string) $result['status'];
            $score = match ($status) {
                DependencyHealthService::STATUS_UP => 1.0,
                DependencyHealthService::STATUS_DEGRADED => 0.5,
                DependencyHealthService::STATUS_DOWN => 0.0,
                default => 0.0,
            };

            $metrics->gauge('dependency_up', $score, ['dep' => $dep]);
            $metrics->gauge('dependency_latency_ms', (float) $result['latency_ms'], ['dep' => $dep]);

            $stateKey = "sre:dep-prev-status:{$dep}";
            $previous = Cache::get($stateKey);
            if ($previous !== null && $previous !== $status) {
                DegradationDetected::dispatch($dep, (string) $previous, $status, (int) $result['latency_ms']);
            }
            Cache::put($stateKey, $status, now()->addHours(24));

            if ($status === DependencyHealthService::STATUS_DOWN) {
                $downAny = true;
                $recorder->record(
                    alertKey: 'dependency_down',
                    value: 0.0,
                    threshold: 0.0,
                    metadata: ['dep' => $dep, 'latency_ms' => $result['latency_ms'], 'error' => $result['error']],
                );
            }

            $this->line(sprintf('%-12s status=%-8s latency=%dms%s', $dep, $status, $result['latency_ms'], $result['error'] ? ' error='.$result['error'] : ''));
        }

        if (! $downAny) {
            $recorder->resolve('dependency_down');
        }

        return self::SUCCESS;
    }
}
