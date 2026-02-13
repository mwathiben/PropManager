<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Http\Requests\GenerateInvoicesRequest;
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
use App\Traits\HasBuildingFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
            ->when($request->arrears_age, function ($q) use ($request) {
                $now = Carbon::now();
                $q->where('status', InvoiceStatus::Overdue);

                switch ($request->arrears_age) {
                    case '0_30':
                        $q->where('due_date', '>=', $now->copy()->subDays(30));
                        break;
                    case '31_60':
                        $q->whereBetween('due_date', [$now->copy()->subDays(60), $now->copy()->subDays(31)]);
                        break;
                    case '61_90':
                        $q->whereBetween('due_date', [$now->copy()->subDays(90), $now->copy()->subDays(61)]);
                        break;
                    case '90_plus':
                        $q->where('due_date', '<', $now->copy()->subDays(90));
                        break;
                }
            })
            ->orderBy('created_at', 'desc')
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

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    public function generate(GenerateInvoicesRequest $request, InvoiceService $invoiceService)
    {
        $billingPeriod = Carbon::create($request->year, $request->month, 1);

        $leases = Lease::where('is_active', true)
            ->with(['unit.building.property', 'tenant'])
            ->get();
        $successCount = 0;

        foreach ($leases as $lease) {
            try {
                $invoiceService->generateInvoiceForLease($lease, $billingPeriod);
                $successCount++;
            } catch (\Exception $e) {
                continue;
            }
        }

        return redirect()->route('invoices.index')->with('success', __('messages.invoice.generated', ['count' => $successCount]));
    }

    public function updateStatus(Request $request, Invoice $invoice)
    {
        $request->validate([
            'status' => ['required', Rule::in(InvoiceStatus::values())],
        ]);

        $oldStatus = $invoice->status;
        $invoice->update(['status' => $request->status]);

        if ($request->status === InvoiceStatus::Sent->value && $oldStatus !== InvoiceStatus::Sent) {
            $invoice->refresh();
            $invoice->load(['lease.tenant', 'lease.unit.building.property']);
            if ($invoice->lease && $invoice->lease->tenant) {
                Mail::to($invoice->lease->tenant)->send(new InvoiceSent($invoice));
            }
        }

        return back()->with('success', __('messages.invoice.status_updated'));
    }

    public function recordPayment(Request $request, Invoice $invoice)
    {
        $this->authorize('recordPayment', $invoice);

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,bank_transfer,mobile_money,paystack',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $remainingBalance = $invoice->total_due - $invoice->amount_paid;
        $paymentAmount = $request->amount;
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

        if ($overpayment > 0) {
            $invoice->lease->creditToWallet(
                $overpayment,
                "Overpayment from payment #{$payment->id}",
                $payment->id
            );
        }

        $receiptService = app(ReceiptService::class);
        $receiptService->createReceipt($payment, $invoice);

        $invoice->load(['lease.tenant', 'lease.unit.building']);
        Mail::to($invoice->lease->tenant->email)->send(new PaymentReceived($payment, $invoice));

        // Send overpayment notification to landlord
        if ($overpayment > 0) {
            $invoice->lease->refresh();
            $landlord = User::find($invoice->landlord_id);
            if ($landlord) {
                Mail::to($landlord->email)->queue(new OverpaymentNotification(
                    $payment,
                    $invoice->lease,
                    $invoice->lease->tenant,
                    $overpayment,
                    $invoice->lease->wallet_balance
                ));
            }
        }

        $currencySymbol = ($invoice->currency ?? Currency::default())->symbol();
        $message = 'Payment of '.$currencySymbol.' '.number_format($paymentAmount, 2).' recorded successfully.';
        if ($overpayment > 0) {
            $message .= ' '.$currencySymbol.' '.number_format($overpayment, 2).' credited to wallet.';
        }

        return back()->with('success', $message);
    }

    public function destroy(Invoice $invoice)
    {
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
            Mail::to($invoice->lease->tenant)->send(new InvoiceReminder($invoice));
        }

        return back()->with('success', __('messages.invoice.reminder_sent'));
    }

    public function download(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

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
        $this->authorize('view', $invoice);

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if (! in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Sent], true)) {
            return back()->withErrors(['error' => __('messages.invoice.cannot_void_status')]);
        }

        if ($invoice->amount_paid > 0) {
            return back()->withErrors(['error' => __('messages.invoice.cannot_void_with_payments')]);
        }

        $invoice->update([
            'status' => InvoiceStatus::Voided,
            'voided_at' => now(),
            'void_reason' => $request->reason,
        ]);

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

        GenerateInvoicePdf::dispatch($newInvoice->id);

        return redirect()->route('invoices.show', $newInvoice)->with('success', __('messages.invoice.reissued'));
    }
}
