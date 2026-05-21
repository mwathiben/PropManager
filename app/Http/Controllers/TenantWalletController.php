<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Services\Wallet\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-76 WALLET-DEEP TENANT-APPLY: a tenant views their own wallet balance(s)
 * + ledger and applies standing credit to their own outstanding invoices. Every
 * action is gated on the invoice's lease.tenant_id === the authed tenant (not
 * merely landlord scope) so a tenant can never touch another tenant's invoice.
 */
class TenantWalletController extends Controller
{
    public function __construct(private readonly WalletService $wallet) {}

    public function index(Request $request): Response
    {
        $lease = $this->activeLease($request);

        if (! $lease) {
            return Inertia::render('TenantFinances/Wallet', [
                'hasLease' => false,
                'balances' => [],
                'ledger' => [],
                'invoices' => [],
            ]);
        }

        return Inertia::render('TenantFinances/Wallet', [
            'hasLease' => true,
            'balances' => collect($this->wallet->balancesFor($lease))
                ->map(fn (float $balance, string $currency) => ['currency' => $currency, 'balance' => $balance])
                ->values(),
            'ledger' => $this->wallet->ledger($lease)->limit(50)->get()->map(fn ($txn) => [
                'id' => $txn->id,
                'type' => $txn->type,
                'amount' => (float) $txn->amount,
                'currency' => $txn->currency->value,
                'reason' => $txn->reason,
                'balance_after' => (float) $txn->balance_after,
                'created_at' => $txn->created_at?->toDateString(),
            ]),
            'invoices' => Invoice::query()
                ->where('lease_id', $lease->id)
                ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Viewed, InvoiceStatus::Partial, InvoiceStatus::Overdue])
                ->whereColumn('amount_paid', '<', 'total_due')
                ->orderBy('due_date')
                ->get()
                ->map(fn (Invoice $inv) => [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'currency' => $inv->currency->value,
                    'outstanding' => $inv->getOutstandingAmount(),
                    'due_date' => $inv->due_date?->toDateString(),
                ]),
        ]);
    }

    public function apply(Request $request): RedirectResponse
    {
        $lease = $this->activeLease($request);
        abort_unless($lease !== null, 403);

        $data = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $invoice = Invoice::find($data['invoice_id']);
        abort_unless($invoice !== null && $invoice->lease_id === $lease->id, 403);

        $applied = $this->wallet->applyToInvoice($invoice, $data['amount'] ?? null);

        if ($applied <= 0) {
            return back()->withErrors(['wallet' => __('tenant.wallet.nothing_applied')]);
        }

        return back()->with('success', __('tenant.wallet.applied', [
            'amount' => number_format($applied, 2),
            'invoice' => $invoice->invoice_number,
        ]));
    }

    private function activeLease(Request $request): ?Lease
    {
        return Lease::where('tenant_id', $request->user()->id)
            ->where('is_active', true)
            ->with(['unit.building.property'])
            ->first();
    }
}
