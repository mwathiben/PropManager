<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\InvoiceStatus;
use App\Exceptions\EntityNotFoundException;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use App\Services\FinanceCacheService;
use App\Services\ReceiptService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BulkPaymentProcessor
{
    private Collection $archivedTenantsMap;

    private Collection $historicalLeasesMap;

    private int $archivedTenantsCreated = 0;

    public function __construct(
        protected ReceiptService $receiptService,
    ) {}

    public function process(int $landlordId, array $validated): BulkPaymentResult
    {
        $mode = $validated['mode'] ?? 'current';

        try {
            return $mode === 'historical'
                ? $this->processHistorical($landlordId, $validated)
                : $this->processCurrent($landlordId, $validated);
        } catch (\Exception $e) {
            Log::error('Bulk import failed', [
                'landlord_id' => $landlordId,
                'mode' => $mode,
                'error' => $e->getMessage(),
            ]);

            return BulkPaymentResult::failed(count($validated['payments']), $e->getMessage());
        }
    }

    private function processCurrent(int $landlordId, array $validated): BulkPaymentResult
    {
        $successCount = 0;
        $failedCount = 0;
        $totalAmount = 0.0;
        $errors = [];

        DB::beginTransaction();

        try {
            $invoicesMap = $this->preloadInvoices($landlordId, $validated['payments']);
            $leasesMap = $this->preloadLeasesForWalletCredit($landlordId, $validated['payments']);

            Payment::withoutEvents(function () use ($landlordId, $validated, $invoicesMap, $leasesMap, &$successCount, &$failedCount, &$totalAmount, &$errors) {
                foreach ($validated['payments'] as $index => $paymentData) {
                    try {
                        $this->processCurrentPayment($landlordId, $paymentData, $invoicesMap, $leasesMap);
                        $successCount++;
                        $totalAmount += (float) $paymentData['amount'];
                    } catch (\Exception $e) {
                        $failedCount++;
                        $errors[] = ['row' => $index + 1, 'error' => $e->getMessage()];
                    }
                }
            });

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return BulkPaymentResult::failed(count($validated['payments']), $e->getMessage());
        }

        if ($successCount > 0) {
            FinanceCacheService::invalidateAndWarm($landlordId);
        }

        if ($failedCount > 0) {
            return BulkPaymentResult::partial($successCount, $failedCount, $totalAmount, $errors);
        }

        return BulkPaymentResult::succeeded($successCount, $totalAmount);
    }

    private function processHistorical(int $landlordId, array $validated): BulkPaymentResult
    {
        $successCount = 0;
        $failedCount = 0;
        $totalAmount = 0.0;
        $this->archivedTenantsCreated = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            $this->archivedTenantsMap = User::where('landlord_id', $landlordId)
                ->where('role', 'tenant')
                ->where('is_archived', true)
                ->get()
                ->keyBy(fn ($t) => strtolower($t->name).'|'.strtolower($t->email));

            $this->historicalLeasesMap = Lease::where('landlord_id', $landlordId)
                ->where('is_active', false)
                ->get()
                ->keyBy(fn ($l) => "{$l->unit_id}|{$l->tenant_id}");

            Payment::withoutEvents(function () use ($landlordId, $validated, &$successCount, &$failedCount, &$totalAmount, &$errors) {
                foreach ($validated['payments'] as $index => $paymentData) {
                    try {
                        $this->processHistoricalPayment($landlordId, $paymentData);
                        $successCount++;
                        $totalAmount += (float) $paymentData['amount'];
                    } catch (\Exception $e) {
                        $failedCount++;
                        $errors[] = ['row' => $index + 1, 'error' => $e->getMessage()];
                    }
                }
            });

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return BulkPaymentResult::failed(count($validated['payments']), $e->getMessage());
        }

        if ($successCount > 0) {
            FinanceCacheService::invalidateAndWarm($landlordId);
        }

        if ($failedCount > 0) {
            return BulkPaymentResult::partial(
                $successCount, $failedCount, $totalAmount, $errors, $this->archivedTenantsCreated
            );
        }

        return BulkPaymentResult::succeeded($successCount, $totalAmount, $this->archivedTenantsCreated);
    }

    private function preloadInvoices(int $landlordId, array $payments): Collection
    {
        $allInvoiceIds = collect($payments)
            ->flatMap(fn ($p) => collect($p['allocations'] ?? [])->pluck('invoice_id'))
            ->unique()
            ->filter()
            ->values()
            ->all();

        return Invoice::where('landlord_id', $landlordId)
            ->whereIn('id', $allInvoiceIds)
            ->lockForUpdate()
            ->with('lease:id,tenant_id')
            ->get()
            ->keyBy('id');
    }

    private function preloadLeasesForWalletCredit(int $landlordId, array $payments): Collection
    {
        $tenantIdsWithWalletCredit = collect($payments)
            ->filter(fn ($p) => ($p['wallet_credit'] ?? 0) > 0 && empty($p['allocations']))
            ->pluck('tenant_id')
            ->unique()
            ->filter()
            ->values()
            ->all();

        if (empty($tenantIdsWithWalletCredit)) {
            return collect();
        }

        return Lease::where('landlord_id', $landlordId)
            ->whereIn('tenant_id', $tenantIdsWithWalletCredit)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->get()
            ->unique('tenant_id')
            ->keyBy('tenant_id');
    }

    private function processCurrentPayment(
        int $landlordId,
        array $paymentData,
        Collection $invoicesMap,
        Collection $leasesMap,
    ): void {
        foreach ($paymentData['allocations'] as $allocation) {
            $this->processAllocation($landlordId, $paymentData, $allocation, $invoicesMap);
        }

        $this->applyWalletCredit($paymentData, $invoicesMap, $leasesMap);
    }

    private function processAllocation(
        int $landlordId,
        array $paymentData,
        array $allocation,
        Collection $invoicesMap,
    ): void {
        $invoice = $invoicesMap->get($allocation['invoice_id']);

        if (! $invoice) {
            throw new EntityNotFoundException('Invoice', $allocation['invoice_id']);
        }

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $invoice->lease_id,
            'landlord_id' => $landlordId,
            'amount' => $allocation['amount'],
            'payment_method' => $paymentData['payment_method'],
            'payment_date' => $paymentData['payment_date'],
            'reference' => $paymentData['reference'] ?? null,
            'notes' => 'Bulk import',
        ]);

        $invoice->increment('amount_paid', $allocation['amount']);
        $invoice->refresh();

        $newStatus = $this->resolveInvoiceStatus($invoice);
        $invoice->update(['status' => $newStatus]);

        $this->receiptService->createReceipt($payment, $invoice);
    }

    private function resolveInvoiceStatus(Invoice $invoice): string
    {
        if ($invoice->amount_paid >= $invoice->total_due) {
            return InvoiceStatus::Paid->value;
        }

        return $invoice->amount_paid > 0
            ? InvoiceStatus::Partial->value
            : $invoice->status;
    }

    private function applyWalletCredit(
        array $paymentData,
        Collection $invoicesMap,
        Collection $leasesMap,
    ): void {
        $walletCredit = $paymentData['wallet_credit'] ?? 0;

        if ($walletCredit <= 0) {
            return;
        }

        $lease = $this->resolveLeaseForWalletCredit($paymentData, $invoicesMap, $leasesMap);

        if ($lease) {
            $lease->creditToWallet($walletCredit, 'Bulk import wallet credit');

            return;
        }

        throw new \RuntimeException(sprintf(
            'Wallet credit could not be applied: no lease found (tenant_id: %s, reference: %s, wallet_credit: %s)',
            $paymentData['tenant_id'] ?? 'null',
            $paymentData['reference'] ?? 'null',
            $walletCredit,
        ));
    }

    private function resolveLeaseForWalletCredit(
        array $paymentData,
        Collection $invoicesMap,
        Collection $leasesMap,
    ): ?Lease {
        if (! empty($paymentData['allocations'])) {
            $firstInvoice = $invoicesMap->get($paymentData['allocations'][0]['invoice_id']);

            return $firstInvoice?->lease;
        }

        return $leasesMap->get($paymentData['tenant_id']);
    }

    private function processHistoricalPayment(int $landlordId, array $paymentData): void
    {
        if ($paymentData['amount'] <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $archivedTenant = $this->findOrCreateArchivedTenant(
            $landlordId,
            $paymentData['unit_id'],
            $paymentData['tenant_name'],
            $paymentData['tenant_email'] ?? null,
        );

        $historicalLease = $this->findOrCreateHistoricalLease(
            $landlordId,
            $paymentData['unit_id'],
            $archivedTenant->id,
            $paymentData['payment_date'],
        );

        Payment::create([
            'invoice_id' => null,
            'lease_id' => $historicalLease->id,
            'landlord_id' => $landlordId,
            'amount' => $paymentData['amount'],
            'payment_method' => $paymentData['payment_method'],
            'payment_date' => $paymentData['payment_date'],
            'reference' => $paymentData['reference'] ?? null,
            'notes' => 'Historical import',
        ]);
    }

    private function findOrCreateArchivedTenant(
        int $landlordId,
        int $unitId,
        string $tenantName,
        ?string $tenantEmail,
    ): User {
        if ($tenantEmail === null) {
            $existingTenant = $this->archivedTenantsMap->first(
                fn ($t) => strtolower($t->name) === strtolower($tenantName)
            );

            if ($existingTenant) {
                return $existingTenant;
            }

            $email = 'archived_'.Str::slug($tenantName).'_'.$unitId.'_'.time().'@placeholder.local';
        } else {
            $email = $tenantEmail;
        }

        $key = strtolower($tenantName).'|'.strtolower($email);
        $existingTenant = $this->archivedTenantsMap->get($key);

        if ($existingTenant) {
            return $existingTenant;
        }

        $tenant = User::create([
            'name' => $tenantName,
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
            'role' => 'tenant',
            'landlord_id' => $landlordId,
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        $this->archivedTenantsMap->put($key, $tenant);
        $this->archivedTenantsCreated++;

        return $tenant;
    }

    private function findOrCreateHistoricalLease(
        int $landlordId,
        int $unitId,
        int $tenantId,
        string $paymentDate,
    ): Lease {
        $key = "{$unitId}|{$tenantId}";
        $existingLease = $this->historicalLeasesMap->get($key);

        if ($existingLease) {
            $date = Carbon::parse($paymentDate);
            if ($existingLease->end_date->lt($date)) {
                $existingLease->update(['end_date' => $paymentDate]);
            }
            if ($existingLease->start_date->gt($date)) {
                $existingLease->update(['start_date' => $paymentDate]);
            }

            return $existingLease;
        }

        $lease = Lease::create([
            'unit_id' => $unitId,
            'tenant_id' => $tenantId,
            'landlord_id' => $landlordId,
            'start_date' => $paymentDate,
            'end_date' => $paymentDate,
            'rent_amount' => 0,
            'deposit_amount' => 0,
            'is_active' => false,
        ]);

        $this->historicalLeasesMap->put($key, $lease);

        return $lease;
    }
}
