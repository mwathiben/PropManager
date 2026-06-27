<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePaymentVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->tenantHasUnverifiedPayment($request) && ! $request->routeIs(...$this->allowedRoutes())) {
            return redirect()->route('tenant.payment-required')
                ->with('warning', 'Please complete your initial payment to access the portal.');
        }

        return $next($request);
    }

    private function tenantHasUnverifiedPayment(Request $request): bool
    {
        $user = $request->user();

        if (! $user || ! $user->isTenant()) {
            return false;
        }

        $verification = $user->lease?->paymentVerification;

        return $verification !== null && ! $verification->isVerified();
    }

    /**
     * @return list<string>
     */
    private function allowedRoutes(): array
    {
        return [
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
    }
}
