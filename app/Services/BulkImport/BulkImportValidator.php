<?php

declare(strict_types=1);

namespace App\Services\BulkImport;

use App\Enums\InvoiceStatus;
use App\Http\Traits\ParsesCSVFiles;
use App\Models\Building;
use App\Models\Invoice;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Validates bulk payment import CSV files.
 *
 * Supports two modes:
 * - 'current': For active tenants with existing leases/invoices
 * - 'historical': For archived/past tenant payments
 *
 * Uses batch pre-loading to eliminate N+1 queries.
 */
class BulkImportValidator
{
    use ParsesCSVFiles;

    private ?string $mode = null;

    private ?int $landlordId = null;

    private ?int $buildingId = null;

    private ?UploadedFile $file = null;

    private Collection $unitsMap;

    private Collection $tenantsMap;

    private Collection $invoicesMap;

    private Collection $tenantInvoicesMap;

    public function __construct()
    {
        $this->unitsMap = collect();
        $this->tenantsMap = collect();
        $this->invoicesMap = collect();
        $this->tenantInvoicesMap = collect();
    }

    public static function make(): self
    {
        return new self;
    }

    public function forMode(string $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function forLandlord(int $landlordId): self
    {
        $this->landlordId = $landlordId;

        return $this;
    }

    public function forBuilding(int $buildingId): self
    {
        $this->buildingId = $buildingId;

        return $this;
    }

    public function withFile(UploadedFile $file): self
    {
        $this->file = $file;

        return $this;
    }

    /**
     * Validate the CSV and return results.
     *
     * @return array{
     *     success: bool,
     *     error?: string,
     *     total_rows?: int,
     *     valid_rows?: int,
     *     invalid_rows?: int,
     *     valid?: array,
     *     invalid?: array,
     *     mode?: string,
     *     building_id?: int
     * }
     */
    public function validate(): array
    {
        // Precondition checks - ensure all required properties are set
        if ($this->file === null) {
            return ['success' => false, 'error' => 'No file provided. Use withFile() before calling validate().'];
        }

        if ($this->buildingId === null) {
            return ['success' => false, 'error' => 'Building ID not set. Use forBuilding() before calling validate().'];
        }

        if ($this->landlordId === null) {
            return ['success' => false, 'error' => 'Landlord ID not set. Use forLandlord() before calling validate().'];
        }

        if ($this->mode === null) {
            return ['success' => false, 'error' => 'Import mode not set. Use forMode() before calling validate().'];
        }

        $building = Building::where('id', $this->buildingId)
            ->where('landlord_id', $this->landlordId)
            ->first();

        if (! $building) {
            return ['success' => false, 'error' => 'Building not found or access denied.'];
        }

        $rows = $this->parseCsv($this->file->getPathname());

        if (empty($rows)) {
            return ['success' => false, 'error' => 'CSV file is empty or invalid format.'];
        }

        $this->preloadData($rows);

        [$validRows, $invalidRows] = $this->validateRows($rows);

        return [
            'success' => true,
            'total_rows' => count($rows),
            'valid_rows' => count($validRows),
            'invalid_rows' => count($invalidRows),
            'valid' => $validRows,
            'invalid' => $invalidRows,
            'mode' => $this->mode,
            'building_id' => $this->buildingId,
        ];
    }

    /**
     * Pre-load all required data to avoid N+1 queries.
     */
    private function preloadData(array $rows): void
    {
        $this->unitsMap = Unit::where('building_id', $this->buildingId)
            ->get()
            ->keyBy(fn ($u) => strtolower(trim($u->unit_number)));

        if ($this->mode === 'current') {
            $this->preloadCurrentModeData($rows);
        }
    }

    private function preloadCurrentModeData(array $rows): void
    {
        $tenantEmails = collect($rows)
            ->map(fn ($r) => strtolower(trim($r['tenant_email'] ?? $r['Tenant Email'] ?? '')))
            ->filter()
            ->unique();

        $invoiceNumbers = collect($rows)
            ->map(fn ($r) => trim($r['invoice_number'] ?? $r['Invoice Number'] ?? ''))
            ->filter()
            ->unique();

        $this->tenantsMap = User::where('landlord_id', $this->landlordId)
            ->where('role', 'tenant')
            ->where('is_archived', false)
            ->whereIn(DB::raw('LOWER(email)'), $tenantEmails)
            ->with('lease')
            ->get()
            ->keyBy(fn ($t) => strtolower($t->email));

        if ($invoiceNumbers->isNotEmpty()) {
            $this->invoicesMap = Invoice::where('landlord_id', $this->landlordId)
                ->whereIn('invoice_number', $invoiceNumbers)
                ->with('lease')
                ->get()
                ->keyBy('invoice_number');
        }

        $tenantIds = $this->tenantsMap->pluck('id')->unique()->filter();
        if ($tenantIds->isNotEmpty()) {
            $this->tenantInvoicesMap = Invoice::where('landlord_id', $this->landlordId)
                ->whereHas('lease', fn ($q) => $q->whereIn('tenant_id', $tenantIds))
                ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Partial, InvoiceStatus::Overdue])
                ->with('lease:id,tenant_id')
                ->orderBy('due_date', 'asc')
                ->get()
                ->groupBy(fn ($inv) => $inv->lease?->tenant_id);
        }
    }

    /**
     * Validate all rows and separate into valid/invalid.
     *
     * @return array{0: array, 1: array} [validRows, invalidRows]
     */
    private function validateRows(array $rows): array
    {
        $validRows = [];
        $invalidRows = [];

        foreach ($rows as $index => $row) {
            $result = $this->validateRow($index, $row);

            if (isset($result['errors'])) {
                $invalidRows[] = $result;
            } else {
                $validRows[] = $result;
            }
        }

        return [$validRows, $invalidRows];
    }

    /**
     * Validate a single row.
     */
    private function validateRow(int $index, array $row): array
    {
        $rowNumber = $index + 2;
        $parsed = $this->parseRowFields($row);

        $basicErrors = $this->validateBasicFields($parsed);

        $unit = $this->unitsMap->get(strtolower($parsed['unit_number']));
        if (! $unit && ! empty($parsed['unit_number'])) {
            $basicErrors[] = "Unit '{$parsed['unit_number']}' not found in selected building";
        }

        if (! empty($basicErrors)) {
            return [
                'row' => $rowNumber,
                'unit_number' => $parsed['unit_number'],
                'tenant_name' => $parsed['tenant_name'],
                'tenant_email' => $parsed['tenant_email'],
                'amount' => $parsed['amount'],
                'errors' => $basicErrors,
            ];
        }

        if ($this->mode === 'historical') {
            return $this->buildHistoricalResult($rowNumber, $unit, $parsed);
        }

        return $this->validateCurrentRow($rowNumber, $unit, $parsed);
    }

    private function parseRowFields(array $row): array
    {
        return [
            'unit_number' => trim($row['unit_number'] ?? $row['Unit Number'] ?? ''),
            'tenant_name' => trim($row['tenant_name'] ?? $row['Tenant Name'] ?? ''),
            'tenant_email' => trim($row['tenant_email'] ?? $row['Tenant Email'] ?? ''),
            'invoice_number' => trim($row['invoice_number'] ?? $row['Invoice Number'] ?? ''),
            'payment_date' => trim($row['payment_date'] ?? $row['Payment Date'] ?? ''),
            'amount' => trim($row['amount'] ?? $row['Amount'] ?? ''),
            'payment_method' => strtolower(trim($row['payment_method'] ?? $row['Payment Method'] ?? '')),
            'reference' => trim($row['reference'] ?? $row['Reference'] ?? ''),
        ];
    }

    private function validateBasicFields(array $parsed): array
    {
        $errors = [];

        if (empty($parsed['unit_number'])) {
            $errors[] = 'Unit Number is required';
        }

        if ($this->mode === 'historical' && empty($parsed['tenant_name'])) {
            $errors[] = 'Tenant Name is required for historical imports';
        }

        if ($this->mode === 'current' && empty($parsed['tenant_email'])) {
            $errors[] = 'Tenant Email is required for current imports';
        } elseif ($this->mode === 'current' && ! empty($parsed['tenant_email']) && ! filter_var($parsed['tenant_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        if (empty($parsed['payment_date'])) {
            $errors[] = 'Payment Date is required';
        } elseif (! strtotime($parsed['payment_date'])) {
            $errors[] = 'Invalid date format (use YYYY-MM-DD)';
        } elseif ($this->mode === 'current' && strtotime($parsed['payment_date']) > time()) {
            $errors[] = 'Payment date cannot be in the future';
        }

        if (empty($parsed['amount'])) {
            $errors[] = 'Amount is required';
        } elseif (! is_numeric($parsed['amount']) || (float) $parsed['amount'] <= 0) {
            $errors[] = 'Amount must be a positive number';
        }

        $validMethods = ['cash', 'mpesa', 'bank_transfer', 'cheque', 'mobile_money'];
        if (empty($parsed['payment_method'])) {
            $errors[] = 'Payment Method is required';
        } elseif (! in_array($parsed['payment_method'], $validMethods)) {
            $errors[] = 'Payment Method must be one of: '.implode(', ', $validMethods);
        }

        return $errors;
    }

    private function buildHistoricalResult(int $rowNumber, ?Unit $unit, array $parsed): array
    {
        return [
            'row' => $rowNumber,
            'unit_id' => $unit?->id,
            'unit_number' => $parsed['unit_number'],
            'tenant_name' => $parsed['tenant_name'],
            'tenant_email' => $parsed['tenant_email'],
            'payment_date' => $parsed['payment_date'],
            'amount' => (float) $parsed['amount'],
            'payment_method' => $parsed['payment_method'],
            'reference' => $parsed['reference'],
            'is_historical' => true,
        ];
    }

    private function validateCurrentRow(int $rowNumber, ?Unit $unit, array $parsed): array
    {
        $tenant = $this->tenantsMap->get(strtolower($parsed['tenant_email']));

        if (! $tenant) {
            return [
                'row' => $rowNumber,
                'unit_number' => $parsed['unit_number'],
                'tenant_name' => $parsed['tenant_name'],
                'tenant_email' => $parsed['tenant_email'],
                'amount' => $parsed['amount'],
                'errors' => ["Active tenant not found with email: {$parsed['tenant_email']}"],
            ];
        }

        $activeLease = $tenant->lease;
        if (! $activeLease || ! $unit || $activeLease->unit_id !== $unit->id) {
            return [
                'row' => $rowNumber,
                'unit_number' => $parsed['unit_number'],
                'tenant_name' => $parsed['tenant_name'],
                'tenant_email' => $parsed['tenant_email'],
                'amount' => $parsed['amount'],
                'errors' => ["Tenant does not have an active lease for unit {$parsed['unit_number']}"],
            ];
        }

        $allocations = $this->calculateAllocations(
            $tenant,
            $parsed['invoice_number'],
            (float) $parsed['amount']
        );

        if (isset($allocations['errors'])) {
            return [
                'row' => $rowNumber,
                'unit_number' => $parsed['unit_number'],
                'tenant_name' => $parsed['tenant_name'],
                'tenant_email' => $parsed['tenant_email'],
                'amount' => $parsed['amount'],
                'errors' => $allocations['errors'],
            ];
        }

        return [
            'row' => $rowNumber,
            'unit_id' => $unit->id,
            'unit_number' => $parsed['unit_number'],
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'tenant_email' => $parsed['tenant_email'],
            'payment_date' => $parsed['payment_date'],
            'amount' => (float) $parsed['amount'],
            'payment_method' => $parsed['payment_method'],
            'reference' => $parsed['reference'],
            'is_historical' => false,
            'allocations' => $allocations['allocations'],
            'wallet_credit' => $allocations['wallet_credit'],
        ];
    }

    /**
     * Calculate invoice allocations for a payment amount.
     *
     * @return array{allocations: array, wallet_credit: float}|array{errors: array}
     */
    private function calculateAllocations(User $tenant, string $invoiceNumber, float $amount): array
    {
        $allocations = [];
        $walletCredit = 0;
        $remainingAmount = $amount;

        if (! empty($invoiceNumber)) {
            $invoice = $this->invoicesMap->get($invoiceNumber);

            if (! $invoice) {
                return ['errors' => ["Invoice not found: {$invoiceNumber}"]];
            }

            if ($invoice->lease && $invoice->lease->tenant_id !== $tenant->id) {
                return ['errors' => ["Invoice {$invoiceNumber} does not belong to this tenant"]];
            }

            $outstanding = $invoice->getOutstandingAmount();
            $allocationAmount = min($remainingAmount, $outstanding);
            $allocations[] = [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => $allocationAmount,
                'outstanding_before' => $outstanding,
            ];
            $remainingAmount -= $allocationAmount;

            if ($remainingAmount > 0) {
                $walletCredit = $remainingAmount;
            }
        } else {
            $invoices = $this->tenantInvoicesMap->get($tenant->id, collect());

            foreach ($invoices as $invoice) {
                if ($remainingAmount <= 0) {
                    break;
                }

                $outstanding = $invoice->getOutstandingAmount();
                if ($outstanding <= 0) {
                    continue;
                }

                $allocationAmount = min($remainingAmount, $outstanding);
                $allocations[] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $allocationAmount,
                    'outstanding_before' => $outstanding,
                ];
                $remainingAmount -= $allocationAmount;
            }

            if ($remainingAmount > 0) {
                $walletCredit = $remainingAmount;
            }
        }

        return ['allocations' => $allocations, 'wallet_credit' => $walletCredit];
    }

    /**
     * Parse CSV file into array of rows using UploadedFile content.
     */
    private function parseCsv(string $path): array
    {
        if ($this->file === null) {
            return [];
        }

        $content = $this->file->get();

        if ($content === false || trim($content) === '') {
            return [];
        }

        return $this->parseCSVContent($content);
    }
}
