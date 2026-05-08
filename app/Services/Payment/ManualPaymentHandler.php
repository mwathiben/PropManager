<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\InvoiceStatus;
use App\Events\PaymentReceived as PaymentReceivedEvent;
use App\Mail\PaymentReceived;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use App\Services\ReceiptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ManualPaymentHandler
{
    public function __construct(
        protected ReceiptService $receiptService,
    ) {}

    public function record(int $landlordId, array $validated): ManualPaymentResult
    {
        $invoice = null;
        $lease = null;
        $overpayment = 0;

        return DB::transaction(function () use ($landlordId, $validated, &$invoice, &$lease, &$overpayment) {
            [$invoice, $lease, $appliedAmount, $overpayment] = $this->resolveInvoiceAndLease(
                $landlordId,
                $validated
            );

            $payment = Payment::create([
                'invoice_id' => $invoice?->id,
                'lease_id' => $lease?->id,
                'landlord_id' => $landlordId,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'payment_date' => $validated['payment_date'],
                'reference' => $validated['reference'] ?? 'MANUAL-'.strtoupper(uniqid()),
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($invoice) {
                $this->updateInvoiceAndHandleOverpayment($invoice, $lease, $appliedAmount, $overpayment, $payment);
            }

            $this->receiptService->createReceipt($payment, $invoice);

            $this->sendNotifications($payment, $invoice);

            return new ManualPaymentResult($payment, $invoice, $overpayment);
        });
    }

    private function resolveInvoiceAndLease(int $landlordId, array $validated): array
    {
        $invoice = null;
        $lease = null;
        $amount = (float) $validated['amount'];
        $appliedAmount = $amount;
        $overpayment = 0.0;

        $hasInvoice = ! empty($validated['invoice_id']) && ! ($validated['is_unallocated'] ?? false);

        if ($hasInvoice) {
            $invoice = Invoice::where('id', $validated['invoice_id'])
                ->where('landlord_id', $landlordId)
                ->lockForUpdate()
                ->firstOrFail();

            $lease = $invoice->lease;

            $remainingBalance = (float) $invoice->total_due - (float) $invoice->amount_paid;
            $appliedAmount = min($amount, $remainingBalance);
            $overpayment = max(0.0, $amount - $remainingBalance);
        } elseif (! empty($validated['tenant_id'])) {
            $tenant = User::where('id', $validated['tenant_id'])
                ->where('landlord_id', $landlordId)
                ->firstOrFail();

            $lease = $tenant->leases()->where('is_active', true)->first();
        }

        return [$invoice, $lease, $appliedAmount, $overpayment];
    }

    private function updateInvoiceAndHandleOverpayment(
        Invoice $invoice,
        ?Lease $lease,
        float $appliedAmount,
        float $overpayment,
        Payment $payment
    ): void {
        $newAmountPaid = $invoice->amount_paid + $appliedAmount;
        $newStatus = $newAmountPaid >= $invoice->total_due ? InvoiceStatus::Paid : InvoiceStatus::Partial;

        $invoice->update([
            'amount_paid' => $newAmountPaid,
            'status' => $newStatus,
        ]);

        if ($overpayment > 0 && $lease) {
            $lease->creditToWallet(
                $overpayment,
                "Overpayment from manual payment #{$payment->id}",
                $payment->id
            );
            $lease->refresh();
        }
    }

    private function sendNotifications(Payment $payment, ?Invoice $invoice): void
    {
        if (! $invoice || ! $invoice->lease?->tenant) {
            return;
        }

        $invoice->load(['lease.tenant', 'lease.unit.building']);

        Mail::to($invoice->lease->tenant->email)->queue(new PaymentReceived($payment, $invoice));
        PaymentReceivedEvent::dispatch($payment, $invoice);
    }
}
