<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\ReconciliationAlert;
use App\Models\PaymentConfiguration;
use App\Models\ReconciliationReport;
use App\Models\User;
use App\Services\Reconciliation\PaymentReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DailyPaymentReconciliation extends Command
{
    protected $signature = 'reconciliation:run-daily
                            {--landlord= : Run reconciliation for a specific landlord ID}
                            {--days=1 : Number of days to look back}
                            {--dry-run : Preview without storing reports or sending alerts}';

    protected $description = 'Run daily payment reconciliation for all Paystack-configured landlords';

    private int $processed = 0;

    private int $withDiscrepancies = 0;

    private int $errors = 0;

    private bool $isDryRun = false;

    private CarbonImmutable $from;

    private CarbonImmutable $to;

    public function handle(PaymentReconciliationService $service): int
    {
        $days = (int) $this->option('days');
        $this->from = CarbonImmutable::yesterday()->startOfDay();
        $this->to = CarbonImmutable::yesterday()->endOfDay();

        if ($days > 1) {
            $this->from = CarbonImmutable::now()->subDays($days)->startOfDay();
        }

        $this->isDryRun = (bool) $this->option('dry-run');
        $landlordIds = $this->getLandlordIds();

        if ($landlordIds->isEmpty()) {
            $this->info('No Paystack-configured landlords found.');

            return self::SUCCESS;
        }

        $prefix = $this->isDryRun ? '[DRY RUN] ' : '';
        $this->info("{$prefix}Reconciling {$landlordIds->count()} landlord(s) for {$this->from->toDateString()} to {$this->to->toDateString()}");

        $bar = $this->output->createProgressBar($landlordIds->count());
        $bar->start();

        foreach ($landlordIds as $landlordId) {
            $this->processLandlord($service, $landlordId);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->outputSummary();

        return $this->errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function getLandlordIds()
    {
        $landlordOption = $this->option('landlord');

        if ($landlordOption) {
            return PaymentConfiguration::withoutGlobalScope('landlord')
                ->where('landlord_id', (int) $landlordOption)
                ->where('paystack_enabled', true)
                ->whereNotNull('paystack_secret_key')
                ->pluck('landlord_id');
        }

        return PaymentConfiguration::withoutGlobalScope('landlord')
            ->where('paystack_enabled', true)
            ->whereNotNull('paystack_secret_key')
            ->join('users', 'payment_configurations.landlord_id', '=', 'users.id')
            ->where('users.is_archived', false)
            ->pluck('payment_configurations.landlord_id');
    }

    private function processLandlord(PaymentReconciliationService $service, int $landlordId): void
    {
        try {
            $result = $service->reconcilePaystack($landlordId, $this->from, $this->to);

            if ($this->isDryRun) {
                $this->processed++;
                if ($result->hasDiscrepancies()) {
                    $this->withDiscrepancies++;
                }

                return;
            }

            $report = ReconciliationReport::storeFromResult($landlordId, 'paystack', $result, [$this->from, $this->to]);

            if ($report->hasDiscrepancies()) {
                $this->sendAlert($report, $landlordId);
                $this->withDiscrepancies++;
            }

            $this->processed++;
        } catch (\Throwable $e) {
            $this->errors++;

            Log::error('Reconciliation failed for landlord', [
                'landlord_id' => $landlordId,
                'error' => $e->getMessage(),
            ]);

            if (! $this->isDryRun) {
                ReconciliationReport::storeFailed($landlordId, 'paystack', $e->getMessage(), [$this->from, $this->to]);
            }
        }
    }

    private function sendAlert(ReconciliationReport $report, int $landlordId): void
    {
        $landlord = User::find($landlordId);

        if ($landlord?->email) {
            Mail::to($landlord->email)->queue(new ReconciliationAlert($report));
            $report->update(['alert_sent' => true]);
        }
    }

    private function outputSummary(): void
    {
        $this->info("Processed: {$this->processed}");
        $this->info("With discrepancies: {$this->withDiscrepancies}");

        if ($this->errors > 0) {
            $this->error("Errors: {$this->errors}");
        }
    }
}
