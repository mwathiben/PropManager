<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebhookDeadLetter;
use App\Services\PaymentHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class HealthCheckController extends Controller
{
    public function payments(Request $request, PaymentHealthService $service): JsonResponse
    {
        return response()->json($service->check($request->boolean('ping')));
    }

    /**
     * OBS-2: real health probe — DB, Redis, queue, webhook DLQ depth.
     * Returns 200 only when every check is green so load balancers /
     * uptime monitors can act on the result. Replaces the previous
     * static literal that said 'ok' regardless of underlying state.
     */
    public function index(): JsonResponse
    {
        $checks = [
            'db' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'webhook_dlq' => $this->checkWebhookDeadLetter(),
            'external_apis' => $this->checkExternalApis(),
        ];

        $allHealthy = collect($checks)->every(fn ($c) => $c['ok'] === true);

        return response()->json([
            'status' => $allHealthy ? 'ok' : 'degraded',
            'version' => '1.0',
            'checks' => $checks,
        ], $allHealthy ? 200 : 503);
    }

    /**
     * Phase-14 OBSERV-3: external payment-gateway reachability. /up
     * returned 200 'ok' while Paystack / M-Pesa / IntaSend were
     * unreachable; the payment path was effectively dead but the
     * load balancer didn't notice. Result is cached for 60s so
     * health-check fan-in doesn't fan out external pings.
     */
    private function checkExternalApis(): array
    {
        try {
            $result = Cache::remember('health:external_apis', 60, function () {
                return app(PaymentHealthService::class)->check(ping: true);
            });

            // Aggregated gateway status (PaymentHealthService combines
            // them). 'ok' / 'degraded' / 'down' — ok is the only green.
            $status = (string) ($result['status'] ?? 'unknown');

            return [
                'ok' => $status === 'ok',
                'status' => $status,
                'gateways' => array_map(
                    fn ($g) => ['status' => $g['status'] ?? 'unknown'],
                    $result['gateways'] ?? [],
                ),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            $reply = Redis::connection('cache')->ping();

            return ['ok' => true, 'reply' => is_string($reply) ? $reply : 'PONG'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Phase-16 QUEUE-3: per-queue depth check. Pre-fix this only sampled
     * the 'default' queue, so a 10k-row backup on 'notifications' showed
     * green here. Iterates the configured queue list and degrades on
     * any per-queue overage.
     */
    private function checkQueue(): array
    {
        try {
            $queues = config('queue.health.queues', ['default']);
            $threshold = (int) config('queue.health.depth_threshold', 1000);

            $perQueue = [];
            $worstOk = true;
            foreach ($queues as $queue) {
                $size = Queue::size($queue);
                $ok = $size <= $threshold;
                $perQueue[$queue] = ['depth' => $size, 'ok' => $ok];
                if (! $ok) {
                    $worstOk = false;
                }
            }

            return [
                'ok' => $worstOk,
                'threshold' => $threshold,
                'queues' => $perQueue,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkWebhookDeadLetter(): array
    {
        try {
            $unresolved = WebhookDeadLetter::withoutGlobalScope('landlord')
                ->whereNull('resolved_at')
                ->count();
            $threshold = (int) config('payments.dead_letter.alert_threshold', 50);

            return [
                'ok' => $unresolved <= $threshold,
                'unresolved' => $unresolved,
                'threshold' => $threshold,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
