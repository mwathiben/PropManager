<?php

namespace App\Services\Tenant;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Collection;

class LedgerTransactionBuilder
{
    private Collection $leaseIds;

    public function __construct(
        private User $tenant,
        private ?string $dateFrom = null,
        private ?string $dateTo = null,
    ) {
        $this->leaseIds = $tenant->leases()->pluck('id');
    }

    public function build(): Collection
    {
        $transactions = $this->buildInvoiceTransactions()
            ->concat($this->buildPaymentTransactions())
            ->concat($this->buildRefundTransactions())
            ->concat($this->buildCreditNoteTransactions())
            ->sortBy('date')
            ->values();

        return $this->calculateRunningBalances($transactions);
    }

    private function buildInvoiceTransactions(): Collection
    {
        return Invoice::whereIn('lease_id', $this->leaseIds)
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'type' => 'invoice',
                'date' => $inv->created_at,
                'description' => "Invoice #{$inv->invoice_number}",
                'reference' => $inv->invoice_number,
                'amount' => $inv->total_due ?? $inv->total_amount ?? 0,
                'status' => $inv->status,
            ]);
    }

    private function buildPaymentTransactions(): Collection
    {
        return Payment::whereIn('lease_id', $this->leaseIds)
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->with('invoice:id,invoice_number')
            ->get()
            ->map(fn ($pmt) => [
                'id' => $pmt->id,
                'type' => 'payment',
                'date' => $pmt->created_at,
                'description' => 'Payment'.($pmt->invoice ? " for Invoice #{$pmt->invoice->invoice_number}" : ''),
                'reference' => $pmt->reference ?? "PAY-{$pmt->id}",
                'amount' => $pmt->amount,
                'status' => $pmt->status ?? 'completed',
            ]);
    }

    private function buildRefundTransactions(): Collection
    {
        return Refund::whereHas('payment', fn ($q) => $q->whereIn('lease_id', $this->leaseIds))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->where('status', 'completed')
            ->get()
            ->map(fn ($ref) => [
                'id' => $ref->id,
                'type' => 'refund',
                'date' => $ref->processed_at ?? $ref->created_at,
                'description' => "Refund - {$ref->reason}",
                'reference' => "REF-{$ref->id}",
                'amount' => $ref->amount,
                'status' => $ref->status,
            ]);
    }

    private function buildCreditNoteTransactions(): Collection
    {
        return CreditNote::whereIn('lease_id', $this->leaseIds)
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->whereIn('status', ['approved', 'applied'])
            ->get()
            ->map(fn ($cn) => [
                'id' => $cn->id,
                'type' => 'credit_note',
                'date' => $cn->approved_at ?? $cn->created_at,
                'description' => "Credit Note - {$cn->reason_label}",
                'reference' => $cn->credit_number,
                'amount' => $cn->applied_amount ?: $cn->amount,
                'status' => $cn->status,
            ]);
    }

    private function calculateRunningBalances(Collection $transactions): Collection
    {
        $runningBalance = 0;

        return $transactions->map(function ($txn) use (&$runningBalance) {
            $isDebit = in_array($txn['type'], ['invoice', 'refund']);

            if ($isDebit) {
                $runningBalance += $txn['amount'];
                $txn['debit'] = $txn['amount'];
                $txn['credit'] = 0;
            } else {
                $runningBalance -= $txn['amount'];
                $txn['debit'] = 0;
                $txn['credit'] = $txn['amount'];
            }

            $txn['running_balance'] = $runningBalance;

            return $txn;
        });
    }
}
