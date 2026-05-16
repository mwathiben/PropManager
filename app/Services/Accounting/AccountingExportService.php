<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase-30 INT-ACCT-EXPORT-1: produce a QuickBooks IIF or Sage CSV
 * batch from a landlord's invoices, payments, and expenses for a
 * given date window. Both formats are ledger-style — every row is
 * a debit or a credit, every transaction must net to zero, and the
 * accountant on the receiving end will reject the file if a date,
 * amount, or account code drifts.
 *
 * The service streams (StreamedResponse) so a multi-thousand-row
 * export does not have to fit in PHP memory; the format-specific
 * row-builder methods are pure functions of the source models +
 * the resolved ChartOfAccount.
 */
class AccountingExportService
{
    public const FORMAT_QUICKBOOKS_IIF = 'iif';
    public const FORMAT_SAGE_CSV = 'sage_csv';

    public const FORMATS = [self::FORMAT_QUICKBOOKS_IIF, self::FORMAT_SAGE_CSV];

    public function __construct(
        private readonly AccountMappingService $mapper,
    ) {}

    public function export(
        int $landlordId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $format,
    ): StreamedResponse {
        if (! in_array($format, self::FORMATS, true)) {
            throw new \InvalidArgumentException("Unsupported accounting export format: {$format}");
        }

        $invoices = $this->fetchInvoices($landlordId, $from, $to);
        $payments = $this->fetchPayments($landlordId, $from, $to);
        $expenses = $this->fetchExpenses($landlordId, $from, $to);

        return $format === self::FORMAT_QUICKBOOKS_IIF
            ? $this->streamIif($invoices, $payments, $expenses, $landlordId, $from, $to)
            : $this->streamSageCsv($invoices, $payments, $expenses, $from, $to);
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @param  Collection<int, Payment>  $payments
     * @param  Collection<int, Expense>  $expenses
     */
    public function streamIif(
        Collection $invoices,
        Collection $payments,
        Collection $expenses,
        int $landlordId,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): StreamedResponse {
        $filename = sprintf('accounting_%s_%s.iif', $from->format('Ymd'), $to->format('Ymd'));

        return new StreamedResponse(function () use ($invoices, $payments, $expenses, $landlordId): void {
            $out = fopen('php://output', 'w');

            $accounts = $this->collectUsedAccounts($invoices, $payments, $expenses, $landlordId);

            fwrite($out, "!ACCNT\tNAME\tACCNTTYPE\n");
            foreach ($accounts as $account) {
                fwrite($out, sprintf(
                    "ACCNT\t%s\t%s\n",
                    $this->escapeIif($account['name']),
                    $this->iifAccountType($account['type']),
                ));
            }

            fwrite($out, "!TRNS\tTRNSTYPE\tDATE\tACCNT\tAMOUNT\tDOCNUM\tMEMO\n");
            fwrite($out, "!SPL\tTRNSTYPE\tDATE\tACCNT\tAMOUNT\tDOCNUM\tMEMO\n");
            fwrite($out, "!ENDTRNS\n");

            foreach ($invoices as $invoice) {
                $this->writeIifTxn(
                    $out,
                    type: 'INVOICE',
                    date: CarbonImmutable::parse($invoice->created_at),
                    docNum: $invoice->invoice_number ?? (string) $invoice->id,
                    memo: 'Invoice '.($invoice->invoice_number ?? $invoice->id),
                    debitAccount: 'Accounts Receivable',
                    creditAccount: $this->mapper->accountForInvoice($invoice)->account_name,
                    amount: $this->normalizeAmount($invoice->total_due),
                );
            }

            foreach ($payments as $payment) {
                $debitAccount = $this->mapper->accountForPayment($payment)->account_name;
                $this->writeIifTxn(
                    $out,
                    type: 'PAYMENT',
                    date: CarbonImmutable::parse($payment->payment_date ?? $payment->created_at),
                    docNum: $payment->reference ?? (string) $payment->id,
                    memo: 'Payment '.($payment->reference ?? $payment->id),
                    debitAccount: $debitAccount,
                    creditAccount: 'Accounts Receivable',
                    amount: $this->normalizeAmount($payment->amount),
                );
            }

            foreach ($expenses as $expense) {
                $expenseAccount = $this->mapper->accountForExpense($expense)->account_name;
                $this->writeIifTxn(
                    $out,
                    type: 'CHECK',
                    date: CarbonImmutable::parse($expense->expense_date ?? $expense->created_at),
                    docNum: $expense->reference ?? (string) $expense->id,
                    memo: substr((string) ($expense->description ?? 'Expense'), 0, 64),
                    debitAccount: $expenseAccount,
                    creditAccount: 'Cash on Hand',
                    amount: $this->normalizeAmount($expense->amount),
                );
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @param  Collection<int, Payment>  $payments
     * @param  Collection<int, Expense>  $expenses
     */
    public function streamSageCsv(
        Collection $invoices,
        Collection $payments,
        Collection $expenses,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): StreamedResponse {
        $filename = sprintf('accounting_%s_%s.csv', $from->format('Ymd'), $to->format('Ymd'));

        return new StreamedResponse(function () use ($invoices, $payments, $expenses): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Type',
                'Account Code',
                'Date',
                'Reference',
                'Details',
                'Net',
                'Tax Code',
                'Tax Amount',
            ]);

            foreach ($invoices as $invoice) {
                $account = $this->mapper->accountForInvoice($invoice);
                fputcsv($out, [
                    'SI',
                    $account->account_code,
                    CarbonImmutable::parse($invoice->created_at)->format('d/m/Y'),
                    $invoice->invoice_number ?? (string) $invoice->id,
                    'Invoice '.($invoice->invoice_number ?? $invoice->id),
                    number_format($this->normalizeAmount($invoice->total_due), 2, '.', ''),
                    'T0',
                    '0.00',
                ]);
            }

            foreach ($payments as $payment) {
                $account = $this->mapper->accountForPayment($payment);
                fputcsv($out, [
                    'SR',
                    $account->account_code,
                    CarbonImmutable::parse($payment->payment_date ?? $payment->created_at)->format('d/m/Y'),
                    $payment->reference ?? (string) $payment->id,
                    'Receipt '.($payment->reference ?? $payment->id),
                    number_format($this->normalizeAmount($payment->amount), 2, '.', ''),
                    'T0',
                    '0.00',
                ]);
            }

            foreach ($expenses as $expense) {
                $account = $this->mapper->accountForExpense($expense);
                fputcsv($out, [
                    'PI',
                    $account->account_code,
                    CarbonImmutable::parse($expense->expense_date ?? $expense->created_at)->format('d/m/Y'),
                    $expense->reference ?? (string) $expense->id,
                    substr((string) ($expense->description ?? 'Expense'), 0, 64),
                    number_format($this->normalizeAmount($expense->amount), 2, '.', ''),
                    'T0',
                    '0.00',
                ]);
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return Collection<int, Invoice>
     */
    private function fetchInvoices(int $landlordId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Invoice::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->get();
    }

    /**
     * @return Collection<int, Payment>
     */
    private function fetchPayments(int $landlordId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Payment::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->whereBetween('payment_date', [$from->startOfDay(), $to->endOfDay()])
            ->get();
    }

    /**
     * @return Collection<int, Expense>
     */
    private function fetchExpenses(int $landlordId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Expense::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->whereBetween('expense_date', [$from->startOfDay(), $to->endOfDay()])
            ->get();
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @param  Collection<int, Payment>  $payments
     * @param  Collection<int, Expense>  $expenses
     * @return list<array{name: string, type: string}>
     */
    private function collectUsedAccounts(
        Collection $invoices,
        Collection $payments,
        Collection $expenses,
        int $landlordId,
    ): array {
        $accounts = [];
        $remember = function (string $name, string $type) use (&$accounts): void {
            $key = $name.'|'.$type;
            $accounts[$key] = ['name' => $name, 'type' => $type];
        };

        $remember('Accounts Receivable', ChartOfAccount::TYPE_ASSET);
        $remember('Cash on Hand', ChartOfAccount::TYPE_ASSET);

        foreach ($invoices as $invoice) {
            $a = $this->mapper->accountForInvoice($invoice);
            $remember($a->account_name, $a->account_type);
        }
        foreach ($payments as $payment) {
            $a = $this->mapper->accountForPayment($payment);
            $remember($a->account_name, $a->account_type);
        }
        foreach ($expenses as $expense) {
            $a = $this->mapper->accountForExpense($expense);
            $remember($a->account_name, $a->account_type);
        }

        unset($landlordId);

        return array_values($accounts);
    }

    private function writeIifTxn(
        $handle,
        string $type,
        CarbonImmutable $date,
        string $docNum,
        string $memo,
        string $debitAccount,
        string $creditAccount,
        float $amount,
    ): void {
        $dateFmt = $date->format('m/d/Y');
        fwrite($handle, sprintf(
            "TRNS\t%s\t%s\t%s\t%s\t%s\t%s\n",
            $type,
            $dateFmt,
            $this->escapeIif($debitAccount),
            number_format($amount, 2, '.', ''),
            $this->escapeIif($docNum),
            $this->escapeIif($memo),
        ));
        fwrite($handle, sprintf(
            "SPL\t%s\t%s\t%s\t%s\t%s\t%s\n",
            $type,
            $dateFmt,
            $this->escapeIif($creditAccount),
            number_format(-$amount, 2, '.', ''),
            $this->escapeIif($docNum),
            $this->escapeIif($memo),
        ));
        fwrite($handle, "ENDTRNS\n");
    }

    private function iifAccountType(string $type): string
    {
        return match ($type) {
            ChartOfAccount::TYPE_ASSET => 'BANK',
            ChartOfAccount::TYPE_LIABILITY => 'AP',
            ChartOfAccount::TYPE_EQUITY => 'EQUITY',
            ChartOfAccount::TYPE_INCOME => 'INC',
            ChartOfAccount::TYPE_EXPENSE => 'EXP',
            default => 'OTHER',
        };
    }

    private function escapeIif(string $value): string
    {
        return str_replace(["\t", "\n", "\r"], ' ', $value);
    }

    private function normalizeAmount(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        return (float) $value;
    }
}
