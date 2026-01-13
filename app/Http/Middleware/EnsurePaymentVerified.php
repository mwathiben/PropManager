<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePaymentVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isTenant()) {
            return $next($request);
        }

        $lease = $user->lease;
        if (! $lease) {
            return $next($request);
        }

        $verification = $lease->paymentVerification;
        if (! $verification) {
            return $next($request);
        }

        if ($verification->isVerified()) {
            return $next($request);
        }

        $allowedRoutes = [
            'tenant.payment-required',
            'tenant.payment.submit',
            'tenant.payment.pay-online',
            'logout',
            'documents.store',
            'documents.download',
            'documents.view',
            'payments.callback',
            'payments.public-key',
        ];

        if ($request->routeIs(...$allowedRoutes)) {
            return $next($request);
        }

        return redirect()->route('tenant.payment-required')
            ->with('warning', 'Please complete your initial payment to access the portal.');
    }
}
