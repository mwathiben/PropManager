<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PaymentConfiguration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentHealthService
{
    private const PING_CACHE_TTL = 300;

    private const PING_TIMEOUT = 5;

    public function check(bool $ping = false): array
    {
        $gateways = [
            'paystack' => $this->getPaystackStatus($ping),
            'mpesa' => $this->getMpesaStatus($ping),
            'intasend' => $this->getIntaSendStatus($ping),
        ];

        return [
            'status' => $this->aggregateStatus($gateways),
            'gateways' => $gateways,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    // SCOPE-D1: Never hydrate encrypted credentials. The route is
    // unauthenticated (throttle-only); previously this loaded every
    // landlord's payment_configurations row including encrypted secrets
    // into memory on every health check. Now we only ask the database
    // for booleans and environment names.

    private function getPaystackStatus(bool $ping): array
    {
        $count = $this->paystackQuery()->count();

        if ($count === 0) {
            return ['status' => 'not_configured', 'configured_count' => 0];
        }

        $result = ['status' => 'configured', 'configured_count' => $count];

        if ($ping) {
            $pingResult = $this->pingUrls(['https://api.paystack.co']);
            $result['status'] = $pingResult['reachable'] ? 'ok' : 'degraded';
            $result['response_time_ms'] = $pingResult['response_time_ms'];
        }

        return $result;
    }

    private function getMpesaStatus(bool $ping): array
    {
        $base = $this->mpesaQuery();
        $count = (clone $base)->count();

        if ($count === 0) {
            return ['status' => 'not_configured', 'configured_count' => 0];
        }

        $result = ['status' => 'configured', 'configured_count' => $count];

        if ($ping) {
            $environments = (clone $base)
                ->whereNotNull('mpesa_environment')
                ->distinct()
                ->pluck('mpesa_environment')
                ->all();

            if ($environments === []) {
                $environments = ['sandbox'];
            }

            $urls = collect($environments)
                ->map(fn (string $env) => config("mpesa.endpoints.{$env}"))
                ->filter()
                ->values()
                ->all();

            $pingResult = $this->pingUrls($urls);
            $result['status'] = $pingResult['reachable'] ? 'ok' : 'degraded';
            $result['response_time_ms'] = $pingResult['response_time_ms'];
        }

        return $result;
    }

    private function getIntaSendStatus(bool $ping): array
    {
        $base = $this->intaSendQuery();
        $count = (clone $base)->count();

        if ($count === 0) {
            return ['status' => 'not_configured', 'configured_count' => 0];
        }

        $result = ['status' => 'configured', 'configured_count' => $count];

        if ($ping) {
            $environments = (clone $base)
                ->whereNotNull('intasend_environment')
                ->distinct()
                ->pluck('intasend_environment')
                ->all();

            if ($environments === []) {
                $environments = ['sandbox'];
            }

            $urls = collect($environments)
                ->map(fn (string $env) => config("intasend.endpoints.{$env}"))
                ->filter()
                ->values()
                ->all();

            $pingResult = $this->pingUrls($urls);
            $result['status'] = $pingResult['reachable'] ? 'ok' : 'degraded';
            $result['response_time_ms'] = $pingResult['response_time_ms'];
        }

        return $result;
    }

    private function paystackQuery(): Builder
    {
        return PaymentConfiguration::withoutGlobalScopes()
            ->where('paystack_enabled', true)
            ->whereNotNull('paystack_public_key')
            ->whereNotNull('paystack_secret_key');
    }

    private function mpesaQuery(): Builder
    {
        return PaymentConfiguration::withoutGlobalScopes()
            ->where(function (Builder $q) {
                $q->where(function (Builder $api) {
                    $api->whereNotNull('mpesa_consumer_key')
                        ->whereNotNull('mpesa_consumer_secret');
                })->orWhere(function (Builder $stk) {
                    $stk->whereNotNull('mpesa_shortcode')
                        ->whereNotNull('mpesa_passkey');
                });
            });
    }

    private function intaSendQuery(): Builder
    {
        return PaymentConfiguration::withoutGlobalScopes()
            ->where('intasend_enabled', true)
            ->whereNotNull('intasend_publishable_key')
            ->whereNotNull('intasend_secret_key');
    }

    private function pingUrls(array $urls): array
    {
        foreach ($urls as $url) {
            $result = $this->pingUrl($url);
            if ($result !== null) {
                return ['reachable' => true, 'response_time_ms' => $result['response_time_ms']];
            }
        }

        return ['reachable' => false, 'response_time_ms' => null];
    }

    private function pingUrl(string $url): ?array
    {
        $cacheKey = 'payment_health_ping_'.md5($url);

        return Cache::remember($cacheKey, self::PING_CACHE_TTL, function () use ($url) {
            $startTime = microtime(true);

            try {
                $response = Http::timeout(self::PING_TIMEOUT)->get($url);

                $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);

                if ($response->status() < 500) {
                    return ['response_time_ms' => $responseTimeMs];
                }

                Log::warning('Payment gateway health ping returned error', [
                    'url' => $url,
                    'status' => $response->status(),
                    'response_time_ms' => $responseTimeMs,
                ]);

                return null;
            } catch (ConnectionException $e) {
                Log::warning('Payment gateway health ping unreachable', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    private function aggregateStatus(array $gateways): string
    {
        $statuses = array_column($gateways, 'status');

        if (in_array('degraded', $statuses, true)) {
            return 'degraded';
        }

        $unique = array_unique($statuses);

        if ($unique === ['not_configured']) {
            return 'not_configured';
        }

        return 'ok';
    }
}
