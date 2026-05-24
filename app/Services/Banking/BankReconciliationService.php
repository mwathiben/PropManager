<?php

namespace App\Services\Banking;

use App\Enums\InvoiceStatus;
use App\Mail\PaymentReceived;
use App\Models\BankReconciliationQueue;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\BillingModelService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BankReconciliationService
{
    public function __construct(
        protected BillingModelService $billingService
    ) {}

    public function processQueue(): int
    {
        $pending = BankReconciliationQueue::pending()
            ->orWhere(function ($q) {
                $q->where('status', 'error')
                    ->where('retry_count', '<', 3)
                    ->where('next_retry_at', '<=', now());
            })
            ->limit(100)
            ->get();

        $processed = 0;

        foreach ($pending as $item) {
            try {
                $this->processItem($item);
                $processed++;
            } catch (\Exception $e) {
                Log::error('Reconciliation processing failed', [
                    'queue_id' => $item->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    public function processItem(BankReconciliationQueue $item): bool
    {
        $item->update(['status' => 'processing']);

        $invoice = $this->attemptMatch($item);

        if (! $invoice) {
            $item->markAsUnmatched();

            return false;
        }

        try {
            $payment = $this->recordPayment($item, $invoice);
            $item->markAsMatched($invoice, $payment);

            return true;
        } catch (\Exception $e) {
            $item->markAsError($e->getMessage());

            return false;
        }
    }

    public function manualMatch(BankReconciliationQueue $item, Invoice $invoice): Payment
    {
        return DB::transaction(function () use ($item, $invoice) {
            $payment = $this->recordPayment($item, $invoice, 'Manually matched');

            $item->markAsMatched($invoice, $payment);

            return $payment;
        });
    }

    public function getPendingItems(?int $landlordId = null): Collection
    {
        $query = BankReconciliationQueue::whereIn('status', ['pending', 'unmatched', 'error'])
            ->orderBy('created_at', 'desc');

        if ($landlordId) {
            $query->where('landlord_id', $landlordId);
        }

        return $query->get();
    }

    public function getStats(?int $landlordId = null): array
    {
        $query = BankReconciliationQueue::query();

        if ($landlordId) {
            $query->where('landlord_id', $landlordId);
        }

        return [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'unmatched' => (clone $query)->where('status', 'unmatched')->count(),
            'matched' => (clone $query)->where('status', 'matched')->count(),
            'errors' => (clone $query)->where('status', 'error')->count(),
            'total_unmatched_amount' => (clone $query)
                ->whereIn('status', ['pending', 'unmatched'])
                ->sum('amount'),
        ];
    }

    public function processQueueForLandlord(int $landlordId): array
    {
        $pending = BankReconciliationQueue::where('landlord_id', $landlordId)
            ->where(function ($q) {
                $q->where('status', 'pending')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'error')
                            ->where('retry_count', '<', 3)
                            ->where(function ($q3) {
                                $q3->whereNull('next_retry_at')
                                    ->orWhere('next_retry_at', '<=', now());
                            });
                    });
            })
            ->limit(100)
            ->get();

        $matched = 0;
        $failed = 0;

        foreach ($pending as $item) {
            try {
                if ($this->processItem($item)) {
                    $matched++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                Log::error('Reconciliation processing failed', [
                    'queue_id' => $item->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        return [
            'processed' => $pending->count(),
            'matched' => $matched,
            'failed' => $failed,
        ];
    }

    private function attemptMatch(BankReconciliationQueue $item): ?Invoice
    {
        $payload = $item->raw_payload ?? [];

        $reference = $payload['reference']
            ?? $payload['BillRefNumber']
            ?? $payload['Narration']
            ?? $payload['description']
            ?? null;

        if ($reference && preg_match('/INV[-\d]+/i', $reference, $matches)) {
            $invoice = Invoice::where('landlord_id', $item->landlord_id)
                ->where('invoice_number', $matches[0])
                ->first();
            if ($invoice) {
                return $invoice;
            }
        }

        return $this->matchByPhone($item, $payload) ?? $this->matchByAmount($item);
    }

    private function matchByPhone(BankReconciliationQueue $item, array $payload): ?Invoice
    {
        $phone = $payload['senderPhone']
            ?? $payload['MSISDN']
            ?? $payload['senderMobile']
            ?? null;

        if (! $phone) {
            return null;
        }

        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '254')) {
            $phone = '0'.substr($phone, 3);
        }

        $tenant = User::where('role', 'tenant')
            ->where('landlord_id', $item->landlord_id)
            ->where(function ($query) use ($phone) {
                $query->where('mobile_number', $phone)
                    ->orWhere('mobile_number', '254'.substr($phone, 1));
            })
            ->first();

        if (! $tenant) {
            return null;
        }

        $lease = $tenant->leases()->where('is_active', true)->first();

        return $lease?->invoices()
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Partial, InvoiceStatus::Overdue])
            ->orderBy('due_date', 'asc')
            ->first();
    }

    private function matchByAmount(BankReconciliationQueue $item): ?Invoice
    {
        $amount = (float) $item->amount;

        if ($amount <= 0) {
            return null;
        }

        $candidates = Invoice::where('landlord_id', $item->landlord_id)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Partial, InvoiceStatus::Overdue])
            ->get();

        return $this->findByOutstandingBalance($candidates, $amount)
            ?? $this->findByInvoiceAmounts($candidates, $amount);
    }

    private function findByOutstandingBalance(Collection $candidates, float $amount): ?Invoice
    {
        foreach ($candidates as $invoice) {
            $outstanding = $invoice->total_due - $invoice->amount_paid;
            if (abs($outstanding - $amount) < 0.01) {
                return $invoice;
            }
        }

        return null;
    }

    private function findByInvoiceAmounts(Collection $candidates, float $amount): ?Invoice
    {
        foreach ($candidates as $invoice) {
            if (abs($invoice->total_due - $amount) < 0.01 || abs($invoice->rent_due - $amount) < 0.01) {
                return $invoice;
            }
        }

        return null;
    }

    private function recordPayment(BankReconciliationQueue $item, Invoice $invoice, ?string $notes = null): Payment
    {
        return DB::transaction(function () use ($item, $invoice, $notes) {
            $invoice = Invoice::where('id', $invoice->id)->lockForUpdate()->first();
            $amount = $item->amount;

            $payment = $invoice->payments()->create([
                'landlord_id' => $invoice->landlord_id,
                'lease_id' => $invoice->lease_id,
                'amount' => $amount,
                'payment_method' => 'bank_transfer',
                'payment_date' => now(),
                'reference' => $item->transaction_reference,
                'bank_code' => $item->bank_code,
                'bank_transaction_id' => $item->transaction_reference,
                'reconciliation_status' => 'matched',
                'reconciliation_matched_at' => now(),
                'notes' => $notes ?? 'Reconciled from queue',
            ]);

            $landlord = User::find($invoice->landlord_id);
            $feeResult = $this->billingService->calculatePlatformFee($amount, $landlord);
            $this->billingService->recordPlatformFee($payment, $feeResult);

            $remainingBalance = $invoice->total_due - $invoice->amount_paid;
            $appliedAmount = min($amount, $remainingBalance);
            $overpayment = max(0, $amount - $remainingBalance);

            $newAmountPaid = $invoice->amount_paid + $appliedAmount;
            $newStatus = $newAmountPaid >= $invoice->total_due ? InvoiceStatus::Paid : InvoiceStatus::Partial;

            $invoice->update([
                'amount_paid' => $newAmountPaid,
                'status' => $newStatus,
            ]);

            // Phase-98: a water-client invoice has no lease/wallet — never deref it.
            if ($overpayment > 0 && $invoice->lease) {
                $invoice->lease->creditToWallet(
                    $overpayment,
                    "Overpayment from reconciled payment #{$payment->id}",
                    $payment->id
                );
            } elseif ($overpayment > 0) {
                \Illuminate\Support\Facades\Log::warning('Water-client invoice overpaid via bank reconciliation; no wallet to absorb it', [
                    'invoice_id' => $invoice->id,
                    'water_connection_id' => $invoice->water_connection_id,
                    'payment_id' => $payment->id,
                    'overpayment' => $overpayment,
                ]);
            }

            // CONC-15: queue, not send. Synchronous Mail::send held InnoDB
            // row locks for the SMTP timeout window, cascading parallel
            // reconciliations. Wrapped in DB::afterCommit so the queued
            // mailable only enqueues once the payment row is durable.
            $invoice->load(['lease.tenant', 'lease.unit.building', 'waterConnection.client', 'waterConnection.unit']);
            \Illuminate\Support\Facades\DB::afterCommit(function () use ($payment, $invoice) {
                $recipientEmail = $invoice->recipientUser()?->email;
                if ($recipientEmail) {
                    Mail::to($recipientEmail)->queue(new PaymentReceived($payment, $invoice));
                }
                \App\Events\PaymentReceived::dispatch($payment, $invoice);
            });

            return $payment;
        });
    }
}
