<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantFinancesController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $lease = $this->getActiveLease($user);

        if (! $lease) {
            return Inertia::render('TenantFinances/Index', [
                'hasLease' => false,
                'balance' => 0,
                'pendingInvoices' => [],
                'recentPayments' => [],
            ]);
        }

        // Phase-81 LATE-FEE-DEPTH-2: read-only projected late fee so the tenant
        // sees what they'd owe if an overdue invoice stays unpaid past grace.
        $lateFees = app(\App\Services\LateFeeService::class);

        $pendingInvoices = Invoice::where('lease_id', $lease->id)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Partial, InvoiceStatus::Overdue])
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function ($i) use ($lateFees) {
                $isOverdue = $i->status === InvoiceStatus::Overdue || ($i->due_date && $i->due_date->isPast());
                $preview = $isOverdue ? $lateFees->previewLateFee($i) : null;

                return [
                    'id' => $i->id,
                    'invoice_number' => $i->invoice_number,
                    'total_due' => $i->total_due,
                    'amount_paid' => $i->amount_paid,
                    'balance' => $i->total_due - $i->amount_paid,
                    'status' => $i->status,
                    'due_date' => $i->due_date?->format('Y-m-d'),
                    'is_overdue' => $isOverdue,
                    'projected_late_fee' => $preview['projected_fee'] ?? null,
                    'grace_days_remaining' => $preview['grace_days_remaining'] ?? null,
                ];
            });

        $totalBalance = $pendingInvoices->sum('balance');

        $recentPayments = Payment::where('lease_id', $lease->id)
            ->orderBy('payment_date', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'payment_method' => $p->payment_method,
                'payment_date' => $p->payment_date?->format('Y-m-d'),
                'reference' => $p->reference,
            ]);

        return Inertia::render('TenantFinances/Index', [
            'hasLease' => true,
            'lease' => [
                'id' => $lease->id,
                'rent_amount' => $lease->rent_amount,
                'wallet_balance' => $lease->wallet_balance,
                'unit' => $lease->unit?->unit_number,
                'building' => $lease->unit?->building?->name,
            ],
            'balance' => $totalBalance,
            'pendingInvoices' => $pendingInvoices,
            'recentPayments' => $recentPayments,
        ]);
    }

    public function pay(Invoice $invoice): Response|\Illuminate\Http\RedirectResponse
    {
        $user = auth()->user();
        $lease = $this->getActiveLease($user);

        if (! $lease || $invoice->lease_id !== $lease->id) {
            abort(403, 'You do not have access to this invoice.');
        }

        if ($invoice->status === InvoiceStatus::Paid) {
            return redirect()->route('tenant.finances.index')
                ->with('info', 'This invoice has already been paid.');
        }

        $landlordId = $lease->unit?->building?->property?->landlord_id ?? $lease->landlord_id;
        $paymentConfig = PaymentConfiguration::where('landlord_id', $landlordId)->first();

        $acceptedMethods = $paymentConfig?->accepted_payment_methods ?? ['cash', 'bank_transfer'];

        $paymentMethods = [];
        foreach ($acceptedMethods as $method) {
            $paymentMethods[] = [
                'id' => $method,
                'label' => $this->getPaymentMethodLabel($method),
                'description' => $this->getPaymentMethodDescription($method),
                'details' => $this->getPaymentMethodDetails($method, $paymentConfig),
            ];
        }

        if ($paymentConfig?->hasIntaSendConfig()) {
            $paymentMethods[] = [
                'id' => 'intasend_mpesa',
                'label' => $this->getPaymentMethodLabel('intasend_mpesa'),
                'description' => $this->getPaymentMethodDescription('intasend_mpesa'),
                'details' => null,
            ];
        }

        return Inertia::render('TenantFinances/Pay', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total_due' => $invoice->total_due,
                'amount_paid' => $invoice->amount_paid,
                'balance' => $invoice->total_due - $invoice->amount_paid,
                'rent_amount' => $invoice->rent_amount,
                'water_charges' => $invoice->water_charges,
                'arrears_amount' => $invoice->arrears_amount,
                'status' => $invoice->status,
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'billing_period_start' => $invoice->billing_period_start?->format('Y-m-d'),
                'billing_period_end' => $invoice->billing_period_end?->format('Y-m-d'),
            ],
            'lease' => [
                'unit' => $lease->unit?->unit_number,
                'building' => $lease->unit?->building?->name,
            ],
            'paymentMethods' => $paymentMethods,
            'paystackPublicKey' => $paymentConfig?->paystack_public_key,
        ]);
    }

    public function history(Request $request): Response
    {
        $user = auth()->user();
        $lease = $this->getActiveLease($user);

        if (! $lease) {
            return Inertia::render('TenantFinances/History', [
                'payments' => [],
                'invoices' => [],
            ]);
        }

        $payments = Payment::where('lease_id', $lease->id)
            ->with('invoice:id,invoice_number')
            ->orderBy('payment_date', 'desc')
            ->paginate(20)
            ->through(fn ($p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'payment_method' => $p->payment_method,
                'payment_date' => $p->payment_date?->format('Y-m-d'),
                'reference' => $p->reference,
                'invoice_number' => $p->invoice?->invoice_number,
            ]);

        $invoices = Invoice::where('lease_id', $lease->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(fn ($i) => [
                'id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'total_due' => $i->total_due,
                'amount_paid' => $i->amount_paid,
                'status' => $i->status,
                'due_date' => $i->due_date?->format('Y-m-d'),
                'created_at' => $i->created_at->format('Y-m-d'),
            ]);

        return Inertia::render('TenantFinances/History', [
            'payments' => $payments,
            'invoices' => $invoices,
        ]);
    }

    private function getActiveLease($user): ?Lease
    {
        return Lease::where('tenant_id', $user->id)
            ->where('is_active', true)
            ->with(['unit.building.property'])
            ->first();
    }

    private function getPaymentMethodLabel(string $method): string
    {
        return match ($method) {
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'mobile_money' => 'M-Pesa',
            'mpesa' => 'M-Pesa',
            'intasend_mpesa' => 'M-Pesa (IntaSend)',
            'paystack' => 'Pay with Card',
            'stripe' => 'Pay with Card',
            default => ucfirst(str_replace('_', ' ', $method)),
        };
    }

    private function getPaymentMethodDescription(string $method): string
    {
        return match ($method) {
            'cash' => 'Pay cash to your landlord or caretaker',
            'bank_transfer' => 'Transfer to the landlord\'s bank account',
            'mobile_money', 'mpesa' => 'Pay instantly via M-Pesa',
            'intasend_mpesa' => 'Pay instantly via M-Pesa STK Push',
            'paystack' => 'Pay securely with your debit or credit card',
            'stripe' => 'Pay securely with your card',
            default => '',
        };
    }

    private function getPaymentMethodDetails(string $method, ?PaymentConfiguration $config): ?array
    {
        if (! $config) {
            return null;
        }

        return match ($method) {
            'bank_transfer' => [
                'bank_name' => $config->bank_name,
                'account_name' => $config->bank_account_name,
                'account_number' => $config->bank_account_number,
                'branch' => $config->bank_branch,
            ],
            'mobile_money', 'mpesa' => [
                'paybill' => $config->mpesa_paybill,
                'account_name' => $config->mpesa_account_name,
            ],
            default => null,
        };
    }
}
