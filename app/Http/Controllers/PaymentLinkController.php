<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Services\PaymentLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PaymentLinkController extends Controller
{
    public function __construct(
        protected PaymentLinkService $paymentLinkService
    ) {}

    public function show(Request $request, string $token): RedirectResponse|Response
    {
        $link = $this->paymentLinkService->resolve($token);

        $invalidResponse = $this->resolveInvalidLinkResponse($link, $request, $token);
        if ($invalidResponse !== null) {
            return $invalidResponse;
        }

        $invoice = $link->invoice;

        $unavailableResponse = $this->resolveUnavailableInvoiceResponse($invoice);
        if ($unavailableResponse !== null) {
            return $unavailableResponse;
        }

        $this->paymentLinkService->trackClick($link, $request);

        $authRedirect = $this->resolveAuthenticatedRedirect($invoice);
        if ($authRedirect !== null) {
            return $authRedirect;
        }

        session(['intended_payment_invoice' => $invoice->id]);

        $currency = $invoice->currency ?? Currency::default();

        return Inertia::render('PaymentLink/Show', [
            'invoice' => $this->buildInvoicePayload($invoice, $currency),
            'tenant' => [
                'name' => $invoice->lease?->tenant?->name,
                'unit' => $invoice->lease?->unit?->unit_number,
                'building' => $invoice->lease?->unit?->building?->name,
            ],
            'landlord' => [
                'name' => $invoice->landlord?->name,
                'business_name' => $invoice->landlord?->business_name,
            ],
            'token' => $token,
            'loginUrl' => route('login', ['redirect' => route('payment.link', $token)]),
        ]);
    }

    private function resolveInvalidLinkResponse(mixed $link, Request $request, string $token): ?Response
    {
        if (! $link) {
            Log::channel('security')->warning('Invalid payment link token accessed', [
                'ip' => $request->ip(),
                'token_prefix' => substr($token, 0, 8).'...',
                'user_agent' => $request->userAgent(),
            ]);

            return Inertia::render('PaymentLink/Invalid', [
                'reason' => 'not_found',
                'message' => 'This payment link is invalid or does not exist.',
            ]);
        }

        if ($link->isRevoked()) {
            Log::channel('security')->info('Revoked payment link accessed', [
                'ip' => $request->ip(),
                'payment_link_id' => $link->id,
                'invoice_id' => $link->invoice_id,
            ]);

            return Inertia::render('PaymentLink/Invalid', [
                'reason' => 'revoked',
                'message' => 'This payment link has been revoked.',
            ]);
        }

        if ($link->isExpired()) {
            Log::channel('security')->info('Expired payment link accessed', [
                'ip' => $request->ip(),
                'payment_link_id' => $link->id,
                'expired_at' => $link->expires_at?->toIso8601String(),
            ]);

            return Inertia::render('PaymentLink/Invalid', [
                'reason' => 'expired',
                'message' => 'This payment link has expired.',
            ]);
        }

        return null;
    }

    private function resolveUnavailableInvoiceResponse(mixed $invoice): ?Response
    {
        $terminalStatuses = [InvoiceStatus::Paid, InvoiceStatus::Cancelled, InvoiceStatus::Voided];

        if (! $invoice || in_array($invoice->status, $terminalStatuses)) {
            $isPaid = $invoice?->status === InvoiceStatus::Paid;

            return Inertia::render('PaymentLink/Invalid', [
                'reason' => $isPaid ? 'paid' : 'unavailable',
                'message' => $isPaid
                    ? 'This invoice has already been paid. Thank you!'
                    : 'This invoice is no longer available.',
            ]);
        }

        return null;
    }

    private function resolveAuthenticatedRedirect(mixed $invoice): ?RedirectResponse
    {
        if (! auth()->check()) {
            return null;
        }

        $user = auth()->user();

        if ($user->id === $invoice->lease?->tenant_id) {
            return redirect()->route('tenant.finances.pay', $invoice->id);
        }

        if ($user->id === $invoice->landlord_id || $user->landlord_id === $invoice->landlord_id) {
            return redirect()->route('invoices.show', $invoice->id);
        }

        return null;
    }

    private function buildInvoicePayload(mixed $invoice, Currency $currency): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'total_due' => $invoice->total_due,
            'amount_paid' => $invoice->amount_paid,
            'balance' => $invoice->total_due - $invoice->amount_paid,
            'status' => $invoice->status,
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'currency' => $currency->value,
            'currency_symbol' => $currency->symbol(),
        ];
    }
}
