<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebhookDeadLetter;
use App\Services\PaymentHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        ];

        $allHealthy = collect($checks)->every(fn ($c) => $c['ok'] === true);

        return response()->json([
            'status' => $allHealthy ? 'ok' : 'degraded',
            'version' => '1.0',
            'checks' => $checks,
        ], $allHealthy ? 200 : 503);
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

    private function checkQueue(): array
    {
        try {
            $size = Queue::size();
            // Treat a default-queue depth above 1000 as degraded — workers
            // are not keeping up.
            $threshold = (int) config('queue.health.depth_threshold', 1000);

            return [
                'ok' => $size <= $threshold,
                'depth' => $size,
                'threshold' => $threshold,
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
