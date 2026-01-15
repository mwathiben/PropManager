<?php

namespace App\Services;

use App\Jobs\GenerateInvoicePdf;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceType;
use App\Models\Lease;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function generateInvoiceForLease(Lease $lease, Carbon $billingPeriod)
    {
        return DB::transaction(function () use ($lease, $billingPeriod) {
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
                'status' => $totalDue == 0 ? 'paid' : 'draft',
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

            GenerateInvoicePdf::dispatch($invoice->id);

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
            ->where('status', '!=', 'paid')
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

            GenerateInvoicePdf::dispatch($invoice->id);

            return $invoice->fresh(['items']);
        });
    }

    protected function buildFirstInvoiceItems(Lease $lease, $settings, array $overrides): array
    {
        $items = [];

        $rentAmount = $this->calculateFirstMonthRent($lease, $settings, $overrides);
        if ($rentAmount > 0) {
            $items[] = [
                'type' => InvoiceItem::TYPE_RENT,
                'description' => $this->getFirstMonthRentDescription($lease, $settings, $overrides),
                'quantity' => 1,
                'unit_price' => $rentAmount,
                'total' => $rentAmount,
            ];
        }

        $deposit = $overrides['deposit'] ?? $lease->deposit_amount ?? 0;
        if ($deposit > 0) {
            $items[] = [
                'type' => InvoiceItem::TYPE_DEPOSIT,
                'description' => 'Security Deposit',
                'quantity' => 1,
                'unit_price' => $deposit,
                'total' => $deposit,
            ];
        }

        $includeLastMonth = $overrides['include_last_month_rent'] ?? $settings->include_last_month_rent ?? false;
        if ($includeLastMonth) {
            $lastMonthRent = $overrides['last_month_rent'] ?? $lease->rent_amount ?? 0;
            if ($lastMonthRent > 0) {
                $items[] = [
                    'type' => InvoiceItem::TYPE_RENT,
                    'description' => 'Last Month Rent (Advance)',
                    'quantity' => 1,
                    'unit_price' => $lastMonthRent,
                    'total' => $lastMonthRent,
                ];
            }
        }

        $adminFee = $overrides['admin_fee'] ?? $settings->admin_fee_amount ?? 0;
        if ($adminFee > 0) {
            $items[] = [
                'type' => InvoiceItem::TYPE_ADMIN_FEE,
                'description' => 'Administrative/Processing Fee',
                'quantity' => 1,
                'unit_price' => $adminFee,
                'total' => $adminFee,
            ];
        }

        $keyDeposit = $overrides['key_deposit'] ?? $settings->key_deposit_amount ?? 0;
        if ($keyDeposit > 0) {
            $items[] = [
                'type' => InvoiceItem::TYPE_KEY_DEPOSIT,
                'description' => 'Key Deposit',
                'quantity' => 1,
                'unit_price' => $keyDeposit,
                'total' => $keyDeposit,
            ];
        }

        $otherCharges = $overrides['other_charges'] ?? [];
        foreach ($otherCharges as $charge) {
            if (($charge['amount'] ?? 0) > 0) {
                $items[] = [
                    'type' => InvoiceItem::TYPE_OTHER,
                    'description' => $charge['description'] ?? 'Other Charge',
                    'quantity' => 1,
                    'unit_price' => $charge['amount'],
                    'total' => $charge['amount'],
                ];
            }
        }

        return $items;
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
