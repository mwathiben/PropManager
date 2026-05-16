<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;

/**
 * Phase-30 INT-ACCT-EXPORT-2: resolve which GL account a given
 * invoice / payment / expense should post to during an accounting
 * export. The lookup walks the per-landlord chart_of_accounts in a
 * fixed order:
 *
 *   1. exact (source_kind, source_key) match
 *   2. fall back to the landlord's default for that side (income or
 *      expense)
 *   3. fall back to a synthetic suspense account ("4000") so the
 *      export never silently drops a transaction
 *
 * The booted-on-write nature of accounting ledgers means a missing
 * mapping is a real bug — callers can use mappingDiagnostics() to
 * surface gaps in the UI before exporting.
 */
class AccountMappingService
{
    public function __construct() {}

    public function accountForInvoice(Invoice $invoice): ChartOfAccount
    {
        return $this->resolveOrSynthetic(
            landlordId: (int) $invoice->landlord_id,
            sourceKind: ChartOfAccount::SOURCE_INVOICE_TYPE,
            sourceKey: $invoice->invoice_type_id !== null ? (string) $invoice->invoice_type_id : null,
            fallbackKind: ChartOfAccount::SOURCE_DEFAULT_INCOME,
            syntheticType: ChartOfAccount::TYPE_INCOME,
            syntheticCode: '4000',
            syntheticName: 'Suspense Income',
        );
    }

    public function accountForPayment(Payment $payment): ChartOfAccount
    {
        return $this->resolveOrSynthetic(
            landlordId: (int) $payment->landlord_id,
            sourceKind: ChartOfAccount::SOURCE_PAYMENT_METHOD,
            sourceKey: $payment->payment_method,
            fallbackKind: ChartOfAccount::SOURCE_DEFAULT_INCOME,
            syntheticType: ChartOfAccount::TYPE_ASSET,
            syntheticCode: '1000',
            syntheticName: 'Cash on Hand',
        );
    }

    public function accountForExpense(Expense $expense): ChartOfAccount
    {
        return $this->resolveOrSynthetic(
            landlordId: (int) $expense->landlord_id,
            sourceKind: ChartOfAccount::SOURCE_EXPENSE_CATEGORY,
            sourceKey: $expense->category_id !== null ? (string) $expense->category_id : null,
            fallbackKind: ChartOfAccount::SOURCE_DEFAULT_EXPENSE,
            syntheticType: ChartOfAccount::TYPE_EXPENSE,
            syntheticCode: '5000',
            syntheticName: 'Suspense Expense',
        );
    }

    /**
     * @return array{
     *     invoice_types_unmapped: int,
     *     expense_categories_unmapped: int,
     *     missing_default_income: bool,
     *     missing_default_expense: bool,
     * }
     */
    public function mappingDiagnostics(int $landlordId): array
    {
        // InvoiceType is a system-wide table (no landlord_id). The
        // diagnostic surface needs to be per-landlord, so we look at
        // the distinct invoice_type_id values that THIS landlord's
        // invoices actually reference — that's the set the export
        // will hit.
        $invoiceTypeIds = \App\Models\Invoice::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->whereNotNull('invoice_type_id')
            ->distinct()
            ->pluck('invoice_type_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $invoiceTypeMapped = ChartOfAccount::query()
            ->where('landlord_id', $landlordId)
            ->where('source_kind', ChartOfAccount::SOURCE_INVOICE_TYPE)
            ->whereIn('source_key', $invoiceTypeIds)
            ->where('is_active', true)
            ->pluck('source_key')
            ->all();

        $expenseCategoryIds = \App\Models\ExpenseCategory::query()
            ->where('landlord_id', $landlordId)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $expenseCategoryMapped = ChartOfAccount::query()
            ->where('landlord_id', $landlordId)
            ->where('source_kind', ChartOfAccount::SOURCE_EXPENSE_CATEGORY)
            ->whereIn('source_key', $expenseCategoryIds)
            ->where('is_active', true)
            ->pluck('source_key')
            ->all();

        return [
            'invoice_types_unmapped' => max(0, count($invoiceTypeIds) - count($invoiceTypeMapped)),
            'expense_categories_unmapped' => max(0, count($expenseCategoryIds) - count($expenseCategoryMapped)),
            'missing_default_income' => ! $this->hasDefault($landlordId, ChartOfAccount::SOURCE_DEFAULT_INCOME),
            'missing_default_expense' => ! $this->hasDefault($landlordId, ChartOfAccount::SOURCE_DEFAULT_EXPENSE),
        ];
    }

    private function resolveOrSynthetic(
        int $landlordId,
        string $sourceKind,
        ?string $sourceKey,
        string $fallbackKind,
        string $syntheticType,
        string $syntheticCode,
        string $syntheticName,
    ): ChartOfAccount {
        if ($sourceKey !== null) {
            $exact = ChartOfAccount::query()
                ->withoutGlobalScopes()
                ->where('landlord_id', $landlordId)
                ->where('source_kind', $sourceKind)
                ->where('source_key', $sourceKey)
                ->where('is_active', true)
                ->first();
            if ($exact !== null) {
                return $exact;
            }
        }

        $fallback = ChartOfAccount::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->where('source_kind', $fallbackKind)
            ->where('is_active', true)
            ->first();
        if ($fallback !== null) {
            return $fallback;
        }

        return new ChartOfAccount([
            'landlord_id' => $landlordId,
            'account_code' => $syntheticCode,
            'account_name' => $syntheticName,
            'account_type' => $syntheticType,
            'is_active' => true,
            'description' => 'Synthetic suspense account — no mapping configured.',
        ]);
    }

    private function hasDefault(int $landlordId, string $sourceKind): bool
    {
        return ChartOfAccount::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->where('source_kind', $sourceKind)
            ->where('is_active', true)
            ->exists();
    }
}
