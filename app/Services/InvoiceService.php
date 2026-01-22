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

class InvoiceService
{
    public function generateInvoiceForLease(Lease $lease, Carbon $billingPeriod)
    {
        return DB::transaction(function () use ($lease, $billingPeriod) {
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

            $walletApplied = 0;
            if ($lease->hasWalletBalance()) {
                $walletApplied = $lease->deductFromWallet($totalDue, 'Applied to invoice');
                $totalDue = max(0, $totalDue - $walletApplied);
            }

            $invoice = Invoice::create([
                'lease_id' => $lease->id,
                'landlord_id' => $lease->unit->building->property->landlord_id,
                'invoice_number' => $this->generateInvoiceNumber(),
                'due_date' => $dueDate,
                'billing_period_start' => $billingPeriod,
                'rent_due' => $rentDue,
                'water_due' => $waterDue,
                'arrears' => $arrears,
                'wallet_applied' => $walletApplied,
                'total_due' => $totalDue,
                'amount_paid' => 0,
                'status' => $totalDue == 0 ? InvoiceStatus::Paid : InvoiceStatus::Draft,
            ]);

            if ($walletApplied > 0) {
                $walletTransaction = $lease->walletTransactions()->latest()->first();
                if ($walletTransaction) {
                    $walletTransaction->update(['invoice_id' => $invoice->id]);
                }
            }

            // Only mark readings as invoiced for consumption-based billing
            $building = $lease->unit->building;
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

        // Consumption-based billing - sum approved, uninvoiced readings
        $readings = WaterReading::where('unit_id', $lease->unit_id)
            ->where('status', 'approved')
            ->where('is_invoiced', false)
            ->whereYear('reading_date', $billingPeriod->year)
            ->whereMonth('reading_date', $billingPeriod->month)
            ->get();

        return $readings->sum('cost');
    }

    protected function markWaterReadingsAsInvoiced(Lease $lease, Carbon $billingPeriod)
    {
        // IMPORTANT: Only mark APPROVED readings as invoiced
        WaterReading::where('unit_id', $lease->unit_id)
            ->where('status', 'approved') // Only approved readings
            ->where('is_invoiced', false)
            ->whereYear('reading_date', $billingPeriod->year)
            ->whereMonth('reading_date', $billingPeriod->month)
            ->update(['is_invoiced' => true]);
    }

    protected function getPreviousArrears(Lease $lease)
    {
        $lastInvoice = Invoice::where('lease_id', $lease->id)
            ->where('status', '!=', InvoiceStatus::Paid)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $lastInvoice) {
            return 0;
        }

        return max(0, $lastInvoice->total_due - $lastInvoice->amount_paid);
    }

    public function generateInvoiceNumber()
    {
        $prefix = 'INV';
        $year = date('Y');
        $month = date('m');
        $pattern = "{$prefix}-{$year}{$month}-%";

        // Use atomic count to prevent race conditions
        // This counts existing invoices with matching pattern to ensure uniqueness
        $count = Invoice::where('invoice_number', 'like', $pattern)
            ->lockForUpdate()
            ->count();

        $nextNumber = $count + 1;

        return sprintf('%s-%s%s-%04d', $prefix, $year, $month, $nextNumber);
    }

    public function generateFirstInvoiceForLease(Lease $lease, array $overrides = []): Invoice
    {
        return DB::transaction(function () use ($lease, $overrides) {
            $landlord = $lease->unit->building->property->landlord;
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
                'status' => Invoice::STATUS_DRAFT,
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
                'status' => $totalDue > 0 ? Invoice::STATUS_DRAFT : Invoice::STATUS_PAID,
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
        $daysRemaining = $daysInMonth - $startDate->day + 1;

        return round(($fullRent / $daysInMonth) * $daysRemaining, 2);
    }

    protected function getFirstMonthRentDescription(Lease $lease, $settings, array $overrides): string
    {
        $shouldProrate = $overrides['prorate'] ?? $settings->prorate_first_month ?? true;
        $startDate = $lease->start_date;

        if (! $shouldProrate || $startDate->day === 1) {
            return 'First Month Rent ('.$startDate->format('F Y').')';
        }

        $endOfMonth = $startDate->copy()->endOfMonth();

        return 'First Month Rent (Prorated: '.$startDate->format('M j').' - '.$endOfMonth->format('M j, Y').')';
    }
}
