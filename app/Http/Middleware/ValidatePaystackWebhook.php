<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidatePaystackWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isIpAllowed($request->ip())) {
            $this->logRejection($request);

            abort(403, 'Forbidden');
        }

        return $next($request);
    }

    private function isIpAllowed(string $ip): bool
    {
        $allowedIps = config('payments.webhook_security.paystack.allowed_ips', []);

        if (empty($allowedIps)) {
            Log::warning('Paystack webhook IP allowlist not configured — rejecting request');

            return false;
        }

        return in_array($ip, $allowedIps, true);
    }

    private function logRejection(Request $request): void
    {
        Log::warning('Paystack webhook rejected', [
            'provider' => 'paystack',
            'ip' => $request->ip(),
            'reason' => 'ip_not_whitelisted',
            'path' => $request->path(),
        ]);
    }
}
