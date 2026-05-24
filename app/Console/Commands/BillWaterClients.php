<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\PaymentConfiguration;
use App\Services\NotificationService;
use App\Services\Water\WaterClientBillingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Phase-97 WATER-CLIENT-BILLING: generate water-client charges for the period.
 * Runs monthly (mirrors invoices:generate). Idempotent per connection+period.
 * Per-landlord try/catch so one landlord's bad data never aborts the whole run
 * (the Phase-88 poison-row lesson). Connections the biller refuses (no rate /
 * metered-without-meter) are logged for the landlord to fix — never billed at 0.
 */
class BillWaterClients extends Command
{
    protected $signature = 'water:bill-clients {--month= : Billing month (Y-m or any date); defaults to the current month}';

    protected $description = 'Generate water-client charges for active connections';

    public function handle(WaterClientBillingService $billing, NotificationService $notifications): int
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

            foreach ($result['billed'] as $charge) {
                $this->notifyClient($notifications, $charge);
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

        $this->info("water:bill-clients: {$billed} charge(s) created, {$skipped} skipped, period {$period->format('Y-m')}");

        return self::SUCCESS;
    }

    /**
     * Notify the onboarded water client that a new bill is ready. A connection with
     * no user_id (not yet onboarded) has no recipient — the charge still stands and
     * surfaces once they onboard. One bad send never aborts the run.
     */
    private function notifyClient(NotificationService $notifications, \App\Models\WaterClientCharge $charge): void
    {
        $connection = $charge->connection;
        if ($connection === null || $connection->user_id === null) {
            return;
        }

        try {
            $notifications->send(
                recipientId: (int) $connection->user_id,
                type: Notification::TYPE_WATER_BILL_DUE,
                subject: __('water.notify.bill_due_subject'),
                message: __('water.notify.bill_due_body', [
                    'identifier' => $connection->identifier,
                    'period' => $charge->billing_period_start->format('M Y'),
                    'amount' => number_format((float) $charge->water_due, 2),
                ]),
                data: ['water_connection_id' => $connection->id, 'water_client_charge_id' => $charge->id],
                landlordId: (int) $charge->landlord_id,
            );
        } catch (\Throwable $e) {
            Log::error('water:bill-clients notification failed', [
                'charge_id' => $charge->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
