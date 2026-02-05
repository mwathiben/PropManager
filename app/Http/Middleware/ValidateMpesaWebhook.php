<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateMpesaWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isIpAllowed($request->ip())) {
            $this->logRejection($request, 'ip_not_whitelisted');

            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Forbidden'], 403);
        }

        if ($this->isTimestampStale($request)) {
            $this->logRejection($request, 'stale_timestamp');

            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Forbidden'], 403);
        }

        return $next($request);
    }

    private function isIpAllowed(string $ip): bool
    {
        $allowedIps = config('mpesa.allowed_ips', []);

        if (empty($allowedIps)) {
            return ! app()->environment('production');
        }

        return in_array($ip, $allowedIps, true);
    }

    private function isTimestampStale(Request $request): bool
    {
        $timestamp = $this->extractTimestamp($request);

        if ($timestamp === null) {
            return false;
        }

        $parsed = Carbon::createFromFormat('YmdHis', $timestamp);

        if (! $parsed) {
            return true;
        }

        $toleranceMinutes = config('payments.webhook_security.mpesa.timestamp_tolerance_minutes', 15);

        return $parsed->diffInMinutes(now()) > $toleranceMinutes;
    }

    private function extractTimestamp(Request $request): ?string
    {
        $items = $request->input('Body.stkCallback.CallbackMetadata.Item');

        if (is_array($items)) {
            foreach ($items as $item) {
                if (($item['Name'] ?? null) === 'TransactionDate') {
                    return (string) $item['Value'];
                }
            }
        }

        $transTime = $request->input('TransTime');

        if ($transTime !== null) {
            return (string) $transTime;
        }

        return null;
    }

    private function logRejection(Request $request, string $reason): void
    {
        Log::warning('M-Pesa webhook rejected', [
            'provider' => 'mpesa',
            'ip' => $request->ip(),
            'reason' => $reason,
            'path' => $request->path(),
        ]);
    }
}
