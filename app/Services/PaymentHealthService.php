<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PaymentConfiguration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentHealthService
{
    private const PING_CACHE_TTL = 300;

    private const PING_TIMEOUT = 5;

    public function check(bool $ping = false): array
    {
        $configs = $this->loadConfigurations();

        $gateways = [
            'paystack' => $this->getGatewayStatus($configs, 'paystack', $ping),
            'mpesa' => $this->getGatewayStatus($configs, 'mpesa', $ping),
            'intasend' => $this->getGatewayStatus($configs, 'intasend', $ping),
        ];

        return [
            'status' => $this->aggregateStatus($gateways),
            'gateways' => $gateways,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function loadConfigurations(): Collection
    {
        return PaymentConfiguration::withoutGlobalScopes()
            ->select([
                'landlord_id',
                'paystack_enabled', 'paystack_public_key', 'paystack_secret_key',
                'mpesa_consumer_key', 'mpesa_consumer_secret',
                'mpesa_shortcode', 'mpesa_passkey', 'mpesa_environment',
                'intasend_enabled', 'intasend_publishable_key',
                'intasend_secret_key', 'intasend_environment',
            ])
            ->get();
    }

    private function getGatewayStatus(Collection $configs, string $gateway, bool $ping): array
    {
        $configured = $this->filterConfigured($configs, $gateway);
        $count = $configured->count();

        if ($count === 0) {
            return [
                'status' => 'not_configured',
                'configured_count' => 0,
            ];
        }

        $result = [
            'status' => 'configured',
            'configured_count' => $count,
        ];

        if ($ping) {
            $urls = $this->getGatewayPingUrls($configured, $gateway);
            $pingResult = $this->pingUrls($urls);
            $result['status'] = $pingResult['reachable'] ? 'ok' : 'degraded';
            $result['response_time_ms'] = $pingResult['response_time_ms'];
        }

        return $result;
    }

    private function filterConfigured(Collection $configs, string $gateway): Collection
    {
        return match ($gateway) {
            'paystack' => $configs->filter->hasPaystackConfig(),
            'mpesa' => $configs->filter(fn (PaymentConfiguration $c) => $c->hasMpesaApiConfig() || $c->hasMpesaSTKConfig()),
            'intasend' => $configs->filter->hasIntaSendConfig(),
        };
    }

    private function getGatewayPingUrls(Collection $configured, string $gateway): array
    {
        return match ($gateway) {
            'paystack' => ['https://api.paystack.co'],
            'mpesa' => $this->getMpesaPingUrls($configured),
            'intasend' => $this->getIntaSendPingUrls($configured),
        };
    }

    private function getMpesaPingUrls(Collection $configured): array
    {
        $environments = $configured
            ->pluck('mpesa_environment')
            ->filter()
            ->unique()
            ->values();

        if ($environments->isEmpty()) {
            $environments = collect(['sandbox']);
        }

        return $environments
            ->map(fn (string $env) => config("mpesa.endpoints.{$env}"))
            ->filter()
            ->values()
            ->all();
    }

    private function getIntaSendPingUrls(Collection $configured): array
    {
        $environments = $configured
            ->pluck('intasend_environment')
            ->filter()
            ->unique()
            ->values();

        if ($environments->isEmpty()) {
            $environments = collect(['sandbox']);
        }

        return $environments
            ->map(fn (string $env) => config("intasend.endpoints.{$env}"))
            ->filter()
            ->values()
            ->all();
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
