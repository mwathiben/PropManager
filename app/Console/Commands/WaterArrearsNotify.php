<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Notification;
use App\Services\NotificationService;
use App\Services\Water\WaterArrearsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Phase-90: warn tenants with an overdue water bill (and that service may be
 * disconnected). Idempotent per invoice + month; one bad row can't abort the run.
 */
class WaterArrearsNotify extends Command
{
    protected $signature = 'water:arrears-notify {--dry-run}';

    protected $description = 'Notify tenants who have an overdue water bill';

    public function handle(WaterArrearsService $arrears, NotificationService $notifications): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;

        foreach ($arrears->overdueWaterInvoices() as $invoice) {
            $tenant = $invoice->lease?->tenant;
            if (! $tenant) {
                continue;
            }

            // Review HIGH: don't claim the idempotency token on a dry-run (it would
            // suppress the real run), and only claim it AFTER a successful send so a
            // failed send is retried on the next run rather than silently skipped.
            $key = sprintf('water-arrears:%d:%s', $invoice->id, now()->format('Y-m'));
            if (Cache::has($key)) {
                continue;
            }

            if ($dryRun) {
                $sent++;

                continue;
            }

            try {
                $notifications->send(
                    recipientId: (int) $tenant->id,
                    type: Notification::TYPE_WATER_ARREARS,
                    subject: __('water.notify.arrears_subject'),
                    message: __('water.notify.arrears_body', [
                        'unit' => $invoice->lease?->unit?->unit_number ?? '',
                    ]),
                    data: ['invoice_id' => $invoice->id],
                    landlordId: (int) $invoice->landlord_id,
                );
                Cache::put($key, true, now()->addDays(40));
                $sent++;
            } catch (\Throwable $e) {
                Log::error('water:arrears-notify invoice failed', [
                    'invoice_id' => $invoice->id,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->info("water:arrears-notify: {$sent} reminder(s) dispatched");

        return self::SUCCESS;
    }
}
