<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\InvoiceSent;
use App\Models\Invoice;
use App\Models\PaymentConfiguration;
use App\Services\Water\WaterClientBillingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Phase-97/98 WATER-CLIENT-BILLING: generate water-client INVOICES for the period
 * (the one invoicing system — no parallel charges table). Runs monthly (mirrors
 * invoices:generate). Idempotent per connection+period. Per-landlord try/catch so
 * one landlord's bad data never aborts the run (the Phase-88 poison-row lesson).
 * Connections the biller refuses (no rate / metered-without-meter) are logged for
 * the landlord to fix — never billed at 0.
 */
class BillWaterClients extends Command
{
    protected $signature = 'water:bill-clients {--month= : Billing month (Y-m or any date); defaults to the current month}';

    protected $description = 'Generate water-client invoices for active connections';

    public function handle(WaterClientBillingService $billing): int
    {
        // Default to the previous (completed) month — that month's consumption is
        // final by the time the scheduled run fires on the 2nd. --month overrides.
        $period = $this->option('month')
            ? CarbonImmutable::parse($this->option('month'))->startOfMonth()
            : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();

        $landlordIds = PaymentConfiguration::query()
            ->where('supplies_water_clients', true)
            ->pluck('landlord_id');

        $billed = 0;
        $skipped = 0;

        foreach ($landlordIds as $landlordId) {
            try {
                $result = $billing->billForPeriod((int) $landlordId, $period);
            } catch (\Throwable $e) {
                Log::error('water:bill-clients landlord failed', [
                    'landlord_id' => $landlordId,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                continue;
            }

            $billed += count($result['billed']);
            $skipped += count($result['skipped']);

            foreach ($result['billed'] as $invoice) {
                $this->emailInvoice($invoice);
            }

            foreach ($result['skipped'] as $skip) {
                // A misconfigured line the landlord must fix (vs. just "nothing read").
                if (in_array($skip['reason'], ['no_rate', 'metered_no_meter', 'error'], true)) {
                    Log::warning('water:bill-clients connection needs attention', [
                        'landlord_id' => $landlordId,
                        'connection_id' => $skip['connection_id'],
                        'identifier' => $skip['identifier'],
                        'reason' => $skip['reason'],
                    ]);
                }
            }
        }

        $this->info("water:bill-clients: {$billed} invoice(s) created, {$skipped} skipped, period {$period->format('Y-m')}");

        return self::SUCCESS;
    }

    /**
     * Email the onboarded water client their new invoice (the same InvoiceSent flow
     * tenants get). A connection with no client account yet (not onboarded) has no
     * recipient — the invoice still stands and surfaces once they onboard. One bad
     * send never aborts the run.
     */
    private function emailInvoice(Invoice $invoice): void
    {
        $client = $invoice->waterConnection?->client;
        if ($client === null || blank($client->email)) {
            return;
        }

        try {
            Mail::to($client->email, $client->name)->queue(new InvoiceSent($invoice));
        } catch (\Throwable $e) {
            Log::error('water:bill-clients invoice email failed', [
                'invoice_id' => $invoice->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
