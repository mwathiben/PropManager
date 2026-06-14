<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Http\Requests\GenerateInvoicesRequest;
use App\Http\Requests\RecordPaymentRequest;
use App\Jobs\GenerateInvoicePdf;
use App\Mail\InvoiceReminder;
use App\Mail\InvoiceSent;
use App\Mail\OverpaymentNotification;
use App\Mail\PaymentReceived;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\User;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Services\ReceiptService;
use App\Support\AuthAbilities;
use App\Traits\HasBuildingFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    use HasBuildingFilter;

    public function index(Request $request)
    {
        $buildingId = $request->filled('building_id') ? (int) $request->building_id : null;
        $wingId = $request->filled('wing_id') ? (int) $request->wing_id : null;

        $query = Invoice::query()
            ->with(['lease.unit.building', 'lease.tenant']);

        // Apply building/wing filter
        if ($buildingId || $wingId) {
            $query = $this->applyBuildingFilterViaLease($query, $buildingId, $wingId);
        }

        $invoices = $query
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where('invoice_number', 'like', '%'.$request->search.'%'))
            ->when($request->arrears_age, fn ($q) => $this->applyArrearsAgeFilter($q, $request->arrears_age))
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(50);

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'buildings' => $this->getBuildingsForFilter(),
            'filters' => $request->only(['status', 'search', 'arrears_age', 'building_id', 'wing_id']),
        ]);
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $invoice->load(['lease.unit', 'lease.tenant', 'lease.unit.building', 'payments']);

        $abilities = AuthAbilities::forRecord(auth()->user(), $invoice, [
            'update', 'delete', 'recordPayment', 'send', 'pay', 'restore',
        ]);

        return Inertia::render('Invoices/Show', [
            'invoice' => array_merge($invoice->toArray(), ['abilities' => $abilities]),
        ]);
    }

    public function generate(GenerateInvoicesRequest $request, InvoiceService $invoiceService)
    {
        $billingPeriod = Carbon::create($request->year, $request->month, 1);

        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? (int) $user->landlord_id : (int) $user->id;

        // Explicit landlord_id filter as defense-in-depth alongside TenantScope.
        // Super admins bypass scope (intentional system-wide generation) — every
        // other role gets scoped to their own landlord.
        $leasesQuery = Lease::where('is_active', true)
            ->with(['unit.building.property', 'tenant']);

        if (! $user->isSuperAdmin()) {
            $leasesQuery->where('landlord_id', $landlordId);
        }

        // PERF-P3: chunkById instead of get() so a landlord with thousands of
        // leases doesn't hydrate the full collection into memory in one HTTP
        // request. 250-row chunks bound peak memory regardless of fleet size.
        $successCount = 0;

        $leasesQuery->chunkById(250, function ($leases) use ($invoiceService, $billingPeriod, &$successCount) {
            foreach ($leases as $lease) {
                try {
                    $invoiceService->generateInvoiceForLease($lease, $billingPeriod);
                    $successCount++;
                } catch (\Exception $e) {
                    continue;
                }
            }
        });

        return redirect()->route('invoices.index')->with('success', __('messages.invoice.generated', ['count' => $successCount]));
    }

    public function updateStatus(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $request->validate([
            'status' => ['required', Rule::in(InvoiceStatus::values())],
        ]);

        $oldStatus = $invoice->status;
        $invoice->update(['status' => $request->status]);

        if ($request->status === InvoiceStatus::Sent->value && $oldStatus !== InvoiceStatus::Sent) {
            $invoice->refresh();
            $invoice->load(['lease.tenant', 'lease.unit.building.property']);
            if ($invoice->lease && $invoice->lease->tenant) {
                Mail::to($invoice->lease->tenant)->queue(new InvoiceSent($invoice));
            }
        }

        return back()->with('success', __('messages.invoice.status_updated'));
    }

    public function recordPayment(RecordPaymentRequest $request, Invoice $invoice, ReceiptService $receiptService)
    {
        $paymentAmount = $request->amount;

        [$payment, $overpayment] = DB::transaction(function () use ($request, $invoice, $receiptService, $paymentAmount) {
            // CONC-5: refetch under lockForUpdate so two concurrent recordPayment
            // calls on the same invoice (double-click, co-landlord users) can't
            // both read amount_paid=0 and overwrite each other's update. The
            // route-bound $invoice was hydrated outside the transaction.
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $remainingBalance = $invoice->total_due - $invoice->amount_paid;
            $appliedAmount = min($paymentAmount, $remainingBalance);
            $overpayment = max(0, $paymentAmount - $remainingBalance);

            $newAmountPaid = $invoice->amount_paid + $appliedAmount;
            $newStatus = $newAmountPaid >= $invoice->total_due ? InvoiceStatus::Paid : InvoiceStatus::Partial;

            $payment = $invoice->payments()->create([
                'landlord_id' => $invoice->landlord_id,
                'lease_id' => $invoice->lease_id,
                'amount' => $paymentAmount,
                'payment_method' => $request->payment_method,
                'payment_date' => now(),
                'reference' => $request->reference,
                'notes' => $request->notes,
            ]);

            $invoice->update([
                'amount_paid' => $newAmountPaid,
                'status' => $newStatus,
            ]);

            // Phase-98: a water-client invoice has no lease (and therefore no wallet),
            // so an overpayment on one cannot be credited to a wallet. Surface it on the
            // flash message instead of crediting a non-existent lease wallet.
            if ($overpayment > 0 && $invoice->lease) {
                $invoice->lease->creditToWallet(
                    $overpayment,
                    "Overpayment from payment #{$payment->id}",
                    $payment->id
                );
            } elseif ($overpayment > 0) {
                Log::warning('Water-client invoice overpayment has no wallet to absorb it', [
                    'invoice_id' => $invoice->id,
                    'water_connection_id' => $invoice->water_connection_id,
                    'payment_id' => $payment->id,
                    'overpayment' => $overpayment,
                ]);
            }

            $receiptService->createReceipt($payment, $invoice);

            return [$payment, $overpayment];
        });

        $invoice->load(['lease.tenant', 'lease.unit.building', 'waterConnection.client', 'waterConnection.unit']);
        $recipient = $invoice->recipientUser();

        if ($recipient?->email) {
            Mail::to($recipient->email)->queue(new PaymentReceived($payment, $invoice));
        } else {
            Log::warning('Skipping payment receipt email: recipient not found', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
            ]);
        }

        if ($overpayment > 0 && $invoice->lease) {
            $invoice->lease->refresh();
            $tenant = $invoice->lease->tenant;
            $landlord = User::find($invoice->landlord_id);
            if ($landlord && $tenant) {
                Mail::to($landlord->email)->queue(new OverpaymentNotification(
                    $payment,
                    $invoice->lease,
                    $tenant,
                    $overpayment,
                    $invoice->lease->wallet_balance
                ));
            }
        }

        $currencySymbol = ($invoice->currency ?? Currency::default())->symbol();
        $message = 'Payment of '.$currencySymbol.' '.number_format($paymentAmount, 2).' recorded successfully.';
        if ($overpayment > 0) {
            $message .= $invoice->lease
                ? ' '.$currencySymbol.' '.number_format($overpayment, 2).' credited to wallet.'
                : ' '.$currencySymbol.' '.number_format($overpayment, 2).' overpaid (no wallet for a water-client account).';
        }

        return back()->with('success', $message);
    }

    public function destroy(Invoice $invoice)
    {
        $this->authorize('delete', $invoice);

        if ($invoice->status === InvoiceStatus::Paid) {
            return back()->withErrors(['error' => __('messages.invoice.cannot_delete_paid')]);
        }

        $invoice->delete();

        return redirect()->route('invoices.index')->with('success', __('messages.invoice.deleted'));
    }

    public function sendReminder(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        if ($invoice->status === InvoiceStatus::Paid) {
            return back()->withErrors(['error' => __('messages.invoice.cannot_remind_paid')]);
        }

        $invoice->load(['lease.tenant', 'lease.unit.building.property']);

        if ($invoice->lease && $invoice->lease->tenant) {
            Mail::to($invoice->lease->tenant)->queue(new InvoiceReminder($invoice));
        }

        return back()->with('success', __('messages.invoice.reminder_sent'));
    }

    public function download(Invoice $invoice, InvoicePdfService $pdfService)
    {
        $this->authorize('view', $invoice);

        // Phase-98: a water-client invoice has no lease; render it through the
        // lease-optional InvoicePdfService rather than the lease-coupled blade below.
        if ($invoice->isWaterClientInvoice()) {
            return $pdfService->downloadPdf($invoice);
        }

        $invoice->load(['lease.tenant', 'lease.unit.building.property']);

        $tenant = $invoice->lease->tenant;
        $unit = $invoice->lease->unit;
        $building = $unit->building;
        $property = $building->property;

        $currency = $invoice->currency ?? $building->getEffectiveCurrency() ?? Currency::default();

        $pdf = Pdf::loadView('invoices.invoice-pdf', [
            'invoice' => $invoice,
            'tenant' => $tenant,
            'unit' => $unit,
            'building' => $building,
            'property' => $property,
            'currency_symbol' => $currency->symbol(),
            'currency_code' => $currency->value,
        ]);

        $filename = $invoice->invoice_number.'.pdf';

        return $pdf->download($filename);
    }

    public function void(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if (! in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Sent], true)) {
            return back()->withErrors(['error' => __('messages.invoice.cannot_void_status')]);
        }

        if ($invoice->amount_paid > 0) {
            return back()->withErrors(['error' => __('messages.invoice.cannot_void_with_payments')]);
        }

        // AUDIT-8: capture the previous status before update so the audit
        // event records the actual transition rather than just the void
        // reason.
        $previousStatus = $invoice->status;

        $invoice->update([
            'status' => InvoiceStatus::Voided,
            'voided_at' => now(),
            'void_reason' => $request->reason,
        ]);

        $invoice->logStatusChange(
            $previousStatus->value,
            InvoiceStatus::Voided->value,
            $request->reason,
        );

        return back()->with('success', __('messages.invoice.voided'));
    }

    public function preview(Invoice $invoice, InvoicePdfService $pdfService)
    {
        $this->authorize('view', $invoice);

        $invoice->load(['lease.tenant', 'lease.unit.building.property', 'items']);

        return $pdfService->streamPdf($invoice);
    }

    public function reissue(Invoice $invoice, InvoiceService $invoiceService)
    {
        $this->authorize('update', $invoice);

        if ($invoice->status !== InvoiceStatus::Voided) {
            return back()->withErrors(['error' => __('messages.invoice.cannot_reissue')]);
        }

        $newInvoice = DB::transaction(function () use ($invoice, $invoiceService) {
            $newInvoice = $invoice->replicate([
                'id',
                'invoice_number',
                'status',
                'voided_at',
                'void_reason',
                'sent_at',
                'viewed_at',
                'amount_paid',
            ]);

            $newInvoice->invoice_number = $invoiceService->generateInvoiceNumber();
            $newInvoice->status = InvoiceStatus::Draft;
            $newInvoice->amount_paid = 0;
            $newInvoice->save();

            foreach ($invoice->items as $item) {
                $newItem = $item->replicate(['id', 'invoice_id']);
                $newItem->invoice_id = $newInvoice->id;
                $newItem->save();
            }

            return $newInvoice;
        });

        GenerateInvoicePdf::dispatch($newInvoice->id);

        return redirect()->route('invoices.show', $newInvoice)->with('success', __('messages.invoice.reissued'));
    }

    private function applyArrearsAgeFilter($query, string $arrearsAge)
    {
        $now = Carbon::now();
        $query->where('status', InvoiceStatus::Overdue);

        match ($arrearsAge) {
            '0_30' => $query->where('due_date', '>=', $now->copy()->subDays(30)),
            '31_60' => $query->whereBetween('due_date', [$now->copy()->subDays(60), $now->copy()->subDays(31)]),
            '61_90' => $query->whereBetween('due_date', [$now->copy()->subDays(90), $now->copy()->subDays(61)]),
            '90_plus' => $query->where('due_date', '<', $now->copy()->subDays(90)),
            default => null,
        };

        return $query;
    }
}
