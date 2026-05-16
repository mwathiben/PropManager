<?php

declare(strict_types=1);

namespace App\Services\Sre;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Phase-32 SRE-DEPS-1: periodic health check of every upstream we
 * depend on. Each check returns one of three states:
 *
 *   - up        : the dependency responded healthy within the budget
 *   - degraded  : responded slow (latency > soft threshold)
 *   - down      : timed out, 5xx, or threw
 *
 * Returned shape is uniform across deps so the cron + the dashboard
 * render code can treat them generically. The 60s Cache::add gate
 * means two callers in the same minute coalesce — we never hammer
 * Daraja just because two cron threads ran.
 *
 * INTENTIONALLY no retry — the check itself must surface degradation,
 * not hide it. The application-side Phase-16 RESIL backoff handles
 * actual user-facing retries.
 */
class DependencyHealthService
{
    public const STATUS_UP = 'up';
    public const STATUS_DEGRADED = 'degraded';
    public const STATUS_DOWN = 'down';

    public const SUPPORTED = ['daraja', 'paystack', 'intasend', 'smtp', 'sms', 'redis'];

    private const TIMEOUT_SECONDS = 5;
    private const DEGRADED_LATENCY_MS = 2_000;

    /**
     * @return array{status: string, latency_ms: int, checked_at: string, error: string|null}
     */
    public function check(string $dep): array
    {
        $cacheKey = "sre:dep-health:{$dep}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = match ($dep) {
            'daraja' => $this->checkHttp('https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'),
            'paystack' => $this->checkHttp('https://api.paystack.co/ping'),
            'intasend' => $this->checkHttp('https://payment.intasend.com/api/v1/status/'),
            'smtp' => $this->checkSmtp(),
            'sms' => $this->checkHttp('https://api.africastalking.com'),
            'redis' => $this->checkRedis(),
            default => $this->down("unknown dep: {$dep}"),
        };

        Cache::add($cacheKey, $result, now()->addSeconds(60));

        return $result;
    }

    private function checkHttp(string $url): array
    {
        $start = microtime(true);
        try {
            $response = Http::connectTimeout(self::TIMEOUT_SECONDS)
                ->timeout(self::TIMEOUT_SECONDS)
                ->withOptions(['http_errors' => false])
                ->get($url);

            $ms = (int) round((microtime(true) - $start) * 1000);
            $status = $response->status();
            if ($status >= 500) {
                return $this->result(self::STATUS_DOWN, $ms, "HTTP {$status}");
            }
            if ($ms > self::DEGRADED_LATENCY_MS) {
                return $this->result(self::STATUS_DEGRADED, $ms, null);
            }

            return $this->result(self::STATUS_UP, $ms, null);
        } catch (\Throwable $e) {
            $ms = (int) round((microtime(true) - $start) * 1000);
            Log::warning('DependencyHealthService HTTP check failed', ['url' => $url, 'error' => $e->getMessage()]);

            return $this->result(self::STATUS_DOWN, $ms, $e->getMessage());
        }
    }

    private function checkRedis(): array
    {
        $start = microtime(true);
        try {
            Redis::ping();
            $ms = (int) round((microtime(true) - $start) * 1000);

            return $this->result(self::STATUS_UP, $ms, null);
        } catch (\Throwable $e) {
            $ms = (int) round((microtime(true) - $start) * 1000);

            return $this->result(self::STATUS_DOWN, $ms, $e->getMessage());
        }
    }

    private function checkSmtp(): array
    {
        // Minimal probe: resolve the configured SMTP host. A full
        // EHLO/AUTH handshake would cost too much on a 5-min cron;
        // DNS-resolve-only catches the most common outage (host
        // unreachable / DNS pointed at deleted server).
        $start = microtime(true);
        $host = (string) config('mail.mailers.smtp.host', 'localhost');
        $record = @gethostbyname($host);
        $ms = (int) round((microtime(true) - $start) * 1000);
        if ($record === $host) {
            return $this->result(self::STATUS_DOWN, $ms, "SMTP host {$host} did not resolve");
        }

        return $this->result(self::STATUS_UP, $ms, null);
    }

    private function down(string $err): array
    {
        return $this->result(self::STATUS_DOWN, 0, $err);
    }

    /**
     * @return array{status: string, latency_ms: int, checked_at: string, error: string|null}
     */
    private function result(string $status, int $ms, ?string $err): array
    {
        return [
            'status' => $status,
            'latency_ms' => $ms,
            'checked_at' => now()->toIso8601String(),
            'error' => $err,
        ];
    }
}
