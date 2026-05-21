<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Jobs\GenerateInvoicePdf;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceType;
use App\Models\Lease;
use App\Models\WaterReading;
use App\Services\Invoice\FirstInvoiceItemBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Invoice Service - Handles invoice generation and status management.
 *
 * Invoice Status Transitions:
 * - Draft: Created but not sent to tenant (initial state)
 * - Sent: Delivered to tenant, awaiting payment
 * - Partial: Some payment received, balance outstanding
 * - Paid: Fully paid (amount_paid >= total_due)
 * - Overdue: Past due_date without full payment
 *
 * WHY wallet is applied before creating invoice:
 * Prepayments (from overpayments on previous invoices) should reduce the amount due
 * immediately. If wallet covers the full bill, invoice is auto-marked Paid to avoid
 * sending unnecessary payment requests.
 *
 * WHY arrears roll forward to next invoice:
 * Unpaid amounts from previous invoices compound into future invoices to ensure
 * tenants cannot escape past-due amounts by simply not paying. This maintains
 * landlord's receivable balance continuity.
 *
 * WHY water readings marked invoiced only for consumption billing:
 * Flat-rate buildings charge a fixed amount regardless of readings, so readings
 * are informational only. Marking them "invoiced" would incorrectly suggest they
 * were billed when the flat rate was charged instead.
 */
class InvoiceService
{
    public function generateInvoiceForLease(Lease $lease, Carbon $billingPeriod)
    {
        return DB::transaction(function () use ($lease, $billingPeriod) {
            // Pessimistic lock prevents race condition where concurrent requests
            // could create duplicate invoices for the same billing period
            $existingInvoice = Invoice::where('lease_id', $lease->id)
                ->whereYear('billing_period_start', $billingPeriod->year)
                ->whereMonth('billing_period_start', $billingPeriod->month)
                ->lockForUpdate()
                ->first();

            if ($existingInvoice) {
                Log::info('InvoiceService: Invoice already exists for billing period', [
                    'lease_id' => $lease->id,
                    'billing_period' => $billingPeriod->format('Y-m'),
                    'existing_invoice_id' => $existingInvoice->id,
                    'existing_invoice_number' => $existingInvoice->invoice_number,
                ]);

                return $existingInvoice;
            }

            $dueDate = $billingPeriod->copy()->addMonth()->startOfMonth()->addDays(5);

            $rentDue = $lease->rent_amount;

            $waterDue = $this->calculateWaterCharges($lease, $billingPeriod);

            $arrears = $this->getPreviousArrears($lease);

            $totalDue = $rentDue + $waterDue + $arrears;

            $building = $lease->unit->building;

            // Phase-76 AUTO-APPLY-2: apply tenant prepayment at invoice creation
            // only when the landlord's mode is on_invoice_create (off + sweep
            // skip here; the wallet:auto-apply cron handles sweep). Routed through
            // WalletService so the deduction matches the invoice currency.
            $walletApplied = 0;
            $invoiceCurrency = $building->getEffectiveCurrency();
            if (app(\App\Services\Wallet\WalletAutoApplyResolver::class)->appliesOnInvoiceCreate($lease->landlord_id)) {
                $walletApplied = app(\App\Services\Wallet\WalletService::class)
                    ->apply($lease, $totalDue, 'Applied to invoice', null, $invoiceCurrency);
                $totalDue = max(0, $totalDue - $walletApplied);
            }

            $invoice = Invoice::create([
                'lease_id' => $lease->id,
                'landlord_id' => $building->property->landlord_id,
                'invoice_number' => $this->generateInvoiceNumber(),
                'due_date' => $dueDate,
                'billing_period_start' => $billingPeriod,
                'rent_due' => $rentDue,
                'water_due' => $waterDue,
                'arrears' => $arrears,
                'wallet_applied' => $walletApplied,
                'total_due' => $totalDue,
                'amount_paid' => 0,
                'currency' => $building->getEffectiveCurrency(),
                // Auto-set to Paid if wallet fully covered bill (no payment action needed)
                'status' => $totalDue == 0 ? InvoiceStatus::Paid : InvoiceStatus::Draft,
            ]);

            if ($walletApplied > 0) {
                $walletTransaction = $lease->walletTransactions()->latest()->first();
                if ($walletTransaction) {
                    $walletTransaction->update(['invoice_id' => $invoice->id]);
                }
            }

            if ($waterDue > 0 && $building->usesConsumptionBilling()) {
                $this->markWaterReadingsAsInvoiced($lease, $billingPeriod);
            }

            GenerateInvoicePdf::dispatch($invoice->id)->afterCommit();

            return $invoice;
        });
    }

    protected function calculateWaterCharges(Lease $lease, Carbon $billingPeriod)
    {
        $building = $lease->unit->building;

        // Check if water billing is enabled for this building
        if (! $building->hasWaterEnabled()) {
            return 0;
        }

        // Flat rate billing - return the building's flat rate
        if ($building->usesFlatRateBilling()) {
            return $building->getWaterChargeForUnit();
        }

        // Consumption-based billing - sum approved, uninvoiced readings up to billing period end
        $billingPeriodEnd = $billingPeriod->copy()->endOfMonth();
        $readings = WaterReading::where('unit_id', $lease->unit_id)
            ->where('status', 'approved')
            ->where('is_invoiced', false)
            ->where('reading_date', '<=', $billingPeriodEnd)
            ->get();

        return $readings->sum('cost');
    }

    protected function markWaterReadingsAsInvoiced(Lease $lease, Carbon $billingPeriod)
    {
        $billingPeriodEnd = $billingPeriod->copy()->endOfMonth();

        WaterReading::where('unit_id', $lease->unit_id)
            ->where('status', 'approved')
            ->where('is_invoiced', false)
            ->where('reading_date', '<=', $billingPeriodEnd)
            ->update(['is_invoiced' => true]);
    }

    /**
     * Get unpaid balance from previous invoices to roll forward.
     *
     * WHY arrears roll forward: Tenants cannot escape past-due amounts by not paying.
     * Unpaid balances compound into future invoices, maintaining landlord's receivable
     * continuity and ensuring the debt remains visible until fully settled.
     */
    protected function getPreviousArrears(Lease $lease)
    {
        $lastInvoice = Invoice::where('lease_id', $lease->id)
            ->where('status', '!=', InvoiceStatus::Paid)
            ->orderBy('billing_period_start', 'desc')
            ->first();

        if (! $lastInvoice) {
            return 0;
        }

        return max(0, $lastInvoice->total_due - $lastInvoice->amount_paid);
    }

    // CONC-1: count()+1 is NOT atomic — even with lockForUpdate, MySQL only
    // locks rows matching the WHERE clause; gap locks require REPEATABLE READ
    // + range scan, and Laravel's default isolation is READ COMMITTED. Two
    // parallel transactions both compute count=N and insert duplicate numbers.
    // The DB-level UNIQUE index on invoice_number is the canonical guarantee;
    // this method scans for the highest existing suffix and proposes the next
    // value. The caller is expected to handle the rare 1062 duplicate-key
    // retry — see Invoice::create call sites that wrap with retryOnDuplicate.
    public function generateInvoiceNumber()
    {
        $prefix = 'INV';
        $year = date('Y');
        $month = date('m');
        $pattern = "{$prefix}-{$year}{$month}-%";

        $count = Invoice::where('invoice_number', 'like', $pattern)
            ->lockForUpdate()
            ->count();

        $nextNumber = $count + 1;

        return sprintf('%s-%s%s-%04d', $prefix, $year, $month, $nextNumber);
    }

    public function generateFirstInvoiceForLease(Lease $lease, array $overrides = []): Invoice
    {
        return DB::transaction(function () use ($lease, $overrides) {
            $building = $lease->unit->building;
            $landlord = $building->property->landlord;
            $settings = $landlord->getOrCreateInvoiceSetting();

            $dueDays = $overrides['due_days'] ?? $settings->first_invoice_due_days ?? 0;
            $dueDate = $lease->start_date->copy()->addDays($dueDays);

            $firstPaymentType = InvoiceType::firstPayment();

            $invoice = Invoice::create([
                'lease_id' => $lease->id,
                'landlord_id' => $landlord->id,
                'invoice_type_id' => $firstPaymentType?->id,
                'invoice_number' => $this->generateInvoiceNumber(),
                'due_date' => $dueDate,
                'billing_period_start' => $lease->start_date,
                'rent_due' => 0,
                'water_due' => 0,
                'arrears' => 0,
                'total_due' => 0,
                'amount_paid' => 0,
                'currency' => $building->getEffectiveCurrency(),
                'status' => InvoiceStatus::Draft,
            ]);

            $items = $this->buildFirstInvoiceItems($lease, $settings, $overrides);
            $sortOrder = 0;
            $totalDue = 0;

            foreach ($items as $item) {
                if ($item['total'] <= 0) {
                    continue;
                }

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_type' => $item['type'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'],
                    'total' => $item['total'],
                    'sort_order' => $sortOrder++,
                    'metadata' => $item['metadata'] ?? null,
                ]);

                $totalDue += $item['total'];
            }

            $invoice->update([
                'total_due' => $totalDue,
                'status' => $totalDue > 0 ? InvoiceStatus::Draft : InvoiceStatus::Paid,
            ]);

            GenerateInvoicePdf::dispatch($invoice->id)->afterCommit();

            return $invoice->fresh(['items']);
        });
    }

    protected function buildFirstInvoiceItems(Lease $lease, $settings, array $overrides): array
    {
        $rentAmount = $this->calculateFirstMonthRent($lease, $settings, $overrides);
        $rentDescription = $this->getFirstMonthRentDescription($lease, $settings, $overrides);

        return FirstInvoiceItemBuilder::forLease($lease)
            ->withSettings($settings)
            ->withOverrides($overrides)
            ->addRentItem($rentAmount, $rentDescription)
            ->addDepositItem()
            ->addLastMonthRentItem()
            ->addAdminFeeItem()
            ->addKeyDepositItem()
            ->addOtherCharges()
            ->getItems();
    }

    protected function calculateFirstMonthRent(Lease $lease, $settings, array $overrides): float
    {
        $fullRent = (float) ($overrides['rent'] ?? $lease->rent_amount ?? 0);

        if ($fullRent <= 0) {
            return 0;
        }

        $shouldProrate = $overrides['prorate'] ?? $settings->prorate_first_month ?? true;

        if (! $shouldProrate) {
            return $fullRent;
        }

        $startDate = $lease->start_date;
        if ($startDate->day === 1) {
            return $fullRent;
        }

        $daysInMonth = $startDate->daysInMonth;
        // Inclusive: start_date counts as a billable day
        $daysRemaining = $daysInMonth - $startDate->day + 1;

        $proratedAmount = round(($fullRent / $daysInMonth) * $daysRemaining, 2);

        return min($proratedAmount, $fullRent);
    }

    protected function getFirstMonthRentDescription(Lease $lease, $settings, array $overrides): string
    {
        $shouldProrate = $overrides['prorate'] ?? $settings->prorate_first_month ?? true;
        $startDate = $lease->start_date;

        if (! $shouldProrate || $startDate->day === 1) {
            return 'First Month Rent ('.$startDate->translatedFormat('F Y').')';
        }

        $endOfMonth = $startDate->copy()->endOfMonth();

        return 'First Month Rent (Prorated: '.$startDate->format('M j').' - '.$endOfMonth->format('M j, Y').')';
    }
}
