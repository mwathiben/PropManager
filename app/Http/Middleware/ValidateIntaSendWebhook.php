<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateIntaSendWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isIpAllowed($request->ip())) {
            $this->logRejection($request, 'ip_not_whitelisted');

            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        return $next($request);
    }

    private function isIpAllowed(string $ip): bool
    {
        $allowedIps = config('intasend.webhook_allowed_ips', []);

        // Fail OPEN on an empty list BY DESIGN: IntaSend's primary control is
        // the per-landlord challenge (verified in the controller), and IntaSend
        // publishes no stable webhook-source IP range — failing closed here
        // would 403 every callback and halt payments. When operators DO set
        // INTASEND_WEBHOOK_ALLOWED_IPS, this enforces it as defense-in-depth.
        if (empty($allowedIps)) {
            return true;
        }

        return in_array($ip, $allowedIps, true);
    }

    private function logRejection(Request $request, string $reason): void
    {
        Log::warning('IntaSend webhook rejected', [
            'provider' => 'intasend',
            'ip' => $request->ip(),
            'reason' => $reason,
            'path' => $request->path(),
        ]);
    }
}
