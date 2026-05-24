<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PaymentConfiguration;
use App\Models\WaterConnection;
use App\Services\Water\WaterAccountService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-97 WATER-CLIENT-BILLING / Phase-99 WATER-CLIENT-PAYMENTS-ONLINE: the water
 * client's own charges + outstanding balance, and (Phase-99) a checkout page to pay
 * an outstanding invoice online through the supplier's configured gateway. When no
 * online gateway is configured the page falls back to "contact the supplier".
 */
class WaterClientFinancesController extends Controller
{
    public function __construct(private WaterAccountService $accountService) {}

    public function index(): Response
    {
        $user = auth()->user();
        abort_unless($user->isWaterClient(), 403);

        $onlinePayEnabled = $this->onlinePayEnabled((int) $user->landlord_id);

        $lines = WaterConnection::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get()
            ->map(fn (WaterConnection $c) => [
                'id' => $c->id,
                'identifier' => $c->identifier,
                'status' => $c->status,
                'outstanding' => Invoice::outstandingForWaterConnection($c->id),
                'charges' => $this->accountService->chargeHistoryForConnection($c),
                'unpaid_invoices' => $this->unpaidInvoices($c->id),
            ]);

        return Inertia::render('WaterClient/Finances', [
            'lines' => $lines->values(),
            'totalOutstanding' => round((float) $lines->sum('outstanding'), 2),
            'supplierName' => $user->landlord?->name,
            'onlinePayEnabled' => $onlinePayEnabled,
        ]);
    }

    /**
     * Phase-99: render the checkout page for one outstanding water-client invoice.
     * The actual charge runs through the gateway-agnostic payments.checkout.initialize
     * endpoint (authorized for the water client via InvoicePolicy::pay).
     */
    public function pay(Invoice $invoice): Response|\Illuminate\Http\RedirectResponse
    {
        $this->authorize('pay', $invoice);

        $invoice->loadMissing('waterConnection');

        $paymentConfig = PaymentConfiguration::where('landlord_id', $invoice->landlord_id)->first();

        return Inertia::render('WaterClient/Pay', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total_due' => $invoice->total_due,
                'amount_paid' => $invoice->amount_paid,
                'balance' => round($invoice->total_due - $invoice->amount_paid, 2),
                'status' => $invoice->status,
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'billing_period_start' => $invoice->billing_period_start?->format('Y-m-d'),
                'currency' => ($invoice->currency ?? \App\Enums\Currency::default())->value,
                'currency_symbol' => ($invoice->currency ?? \App\Enums\Currency::default())->symbol(),
            ],
            'line' => [
                'identifier' => $invoice->waterConnection?->identifier,
            ],
            'onlinePayEnabled' => $this->onlinePayEnabled((int) $invoice->landlord_id, ($invoice->currency ?? \App\Enums\Currency::default())->value),
            'supplierName' => $invoice->waterConnection?->landlord?->name,
        ]);
    }

    /** Outstanding (unpaid/partial) invoices for a connection, oldest first. */
    private function unpaidInvoices(int $connectionId): array
    {
        return Invoice::withoutGlobalScope('landlord')
            ->where('water_connection_id', $connectionId)
            ->whereNull('voided_at')
            ->whereRaw('amount_paid < total_due')
            ->orderBy('billing_period_start')
            ->get()
            ->map(fn (Invoice $i) => [
                'id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'balance' => round($i->total_due - $i->amount_paid, 2),
                'due_date' => $i->due_date?->format('Y-m-d'),
            ])
            ->all();
    }

    /**
     * Online self-pay is offered only when the supplier's checkout will return a
     * hosted redirect URL the Pay page can follow — i.e. Paystack resolves for this
     * currency. Stripe returns a client_secret (needs Elements, not wired here), so
     * a Stripe/non-KES route correctly falls back to "contact the supplier" rather
     * than showing a dead button.
     */
    private function onlinePayEnabled(int $landlordId, string $currency = 'KES'): bool
    {
        $config = PaymentConfiguration::where('landlord_id', $landlordId)->first();
        if (! $config?->hasPaystackConfig()) {
            return false;
        }

        // Mirror PaymentGatewayManager::routeForUser: the checkout routes to Paystack
        // (the only hosted-redirect gateway the Pay page can follow) when the supplier
        // prefers Paystack, or prefers 'auto' and the invoice is in KES.
        $pref = \App\Models\User::find($landlordId)?->payment_gateway_preference ?? 'auto';

        return $pref === 'paystack'
            || ($pref === 'auto' && strtoupper($currency) === \App\Enums\Currency::KES->value);
    }
}
