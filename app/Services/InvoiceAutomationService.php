<?php

namespace App\Services;

use App\Mail\InvoiceSent;
use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InvoiceAutomationService
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected LateFeeService $lateFeeService
    ) {}

    public function processAutomatedInvoices(?int $day = null): array
    {
        $day = $day ?? now()->day;

        $results = [
            'buildings_processed' => 0,
            'invoices_generated' => 0,
            'invoices_sent' => 0,
            'late_fees_applied' => 0,
            'errors' => [],
        ];

        $buildings = Building::where('auto_generate_invoices', true)
            ->where('invoice_generation_day', $day)
            ->with(['units.activeLease.tenant'])
            ->get();

        foreach ($buildings as $building) {
            $results['buildings_processed']++;

            try {
                $buildingResults = $this->processBuilding($building);
                $results['invoices_generated'] += $buildingResults['invoices_generated'];
                $results['invoices_sent'] += $buildingResults['invoices_sent'];
                $results['late_fees_applied'] += $buildingResults['late_fees_applied'];
            } catch (\Exception $e) {
                Log::error('Invoice automation error', [
                    'building_id' => $building->id,
                    'error' => $e->getMessage(),
                ]);
                $results['errors'][] = [
                    'building_id' => $building->id,
                    'building_name' => $building->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $lateFeeResults = $this->lateFeeService->processAllOverdueInvoices();
        $results['late_fees_applied'] += $lateFeeResults['fees_applied'];

        return $results;
    }

    protected function processBuilding(Building $building): array
    {
        $results = [
            'invoices_generated' => 0,
            'invoices_sent' => 0,
            'late_fees_applied' => 0,
        ];

        $billingPeriod = $this->determineBillingPeriod();

        $activeLeases = $this->getActiveLeasesForBuilding($building);

        foreach ($activeLeases as $lease) {
            $invoice = $this->generateInvoiceForLease($lease, $billingPeriod);

            if ($invoice) {
                $results['invoices_generated']++;

                if ($building->shouldAutoSendInvoices()) {
                    $this->sendInvoiceEmail($invoice);
                    $results['invoices_sent']++;
                }
            }
        }

        return $results;
    }

    protected function getActiveLeasesForBuilding(Building $building): \Illuminate\Support\Collection
    {
        return Lease::where('is_active', true)
            ->whereHas('unit', function ($query) use ($building) {
                $query->where('building_id', $building->id);
            })
            ->with(['tenant', 'unit.building.property'])
            ->get();
    }

    protected function determineBillingPeriod(): Carbon
    {
        return now()->startOfMonth();
    }

    protected function generateInvoiceForLease(Lease $lease, Carbon $billingPeriod): ?Invoice
    {
        $existingInvoice = Invoice::where('lease_id', $lease->id)
            ->whereYear('billing_period_start', $billingPeriod->year)
            ->whereMonth('billing_period_start', $billingPeriod->month)
            ->whereNot('status', Invoice::STATUS_VOID)
            ->exists();

        if ($existingInvoice) {
            Log::info('Skipping invoice generation - already exists', [
                'lease_id' => $lease->id,
                'billing_period' => $billingPeriod->format('Y-m'),
            ]);

            return null;
        }

        return DB::transaction(function () use ($lease, $billingPeriod) {
            return $this->invoiceService->generateInvoiceForLease($lease, $billingPeriod);
        });
    }

    protected function sendInvoiceEmail(Invoice $invoice): void
    {
        $tenant = $invoice->lease->tenant;

        if (! $tenant || ! $tenant->email) {
            Log::warning('Cannot send invoice email - no tenant email', [
                'invoice_id' => $invoice->id,
            ]);

            return;
        }

        Mail::to($tenant->email)->queue(new InvoiceSent($invoice));
        $invoice->markAsSent();
    }

    public function processForBuilding(Building $building, ?Carbon $billingPeriod = null): array
    {
        $billingPeriod = $billingPeriod ?? $this->determineBillingPeriod();

        $results = [
            'invoices_generated' => 0,
            'invoices_sent' => 0,
            'errors' => [],
        ];

        $activeLeases = $this->getActiveLeasesForBuilding($building);

        foreach ($activeLeases as $lease) {
            try {
                $invoice = $this->generateInvoiceForLease($lease, $billingPeriod);

                if ($invoice) {
                    $results['invoices_generated']++;

                    if ($building->shouldAutoSendInvoices()) {
                        $this->sendInvoiceEmail($invoice);
                        $results['invoices_sent']++;
                    }
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'lease_id' => $lease->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function getBuildingsForDay(int $day): \Illuminate\Database\Eloquent\Collection
    {
        return Building::where('auto_generate_invoices', true)
            ->where('invoice_generation_day', $day)
            ->with(['property', 'units' => function ($query) {
                $query->whereHas('activeLease');
            }])
            ->get();
    }

    public function previewAutomation(Building $building): array
    {
        $activeLeases = $this->getActiveLeasesForBuilding($building);
        $billingPeriod = $this->determineBillingPeriod();

        $preview = [
            'building' => [
                'id' => $building->id,
                'name' => $building->name,
                'auto_generate_invoices' => $building->auto_generate_invoices,
                'invoice_generation_day' => $building->invoice_generation_day,
                'auto_send_invoices' => $building->auto_send_invoices,
            ],
            'billing_period' => $billingPeriod->format('F Y'),
            'units_to_invoice' => [],
            'units_already_invoiced' => [],
        ];

        foreach ($activeLeases as $lease) {
            $existingInvoice = Invoice::where('lease_id', $lease->id)
                ->whereYear('billing_period_start', $billingPeriod->year)
                ->whereMonth('billing_period_start', $billingPeriod->month)
                ->whereNot('status', Invoice::STATUS_VOID)
                ->first();

            $unitInfo = [
                'unit_number' => $lease->unit->unit_number,
                'tenant_name' => $lease->tenant->name ?? 'Unknown',
                'rent_amount' => $lease->rent_amount,
            ];

            if ($existingInvoice) {
                $unitInfo['invoice_number'] = $existingInvoice->invoice_number;
                $unitInfo['status'] = $existingInvoice->status;
                $preview['units_already_invoiced'][] = $unitInfo;
            } else {
                $preview['units_to_invoice'][] = $unitInfo;
            }
        }

        return $preview;
    }
}
