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
            $this->info('No gateway-configured landlords found.');

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

        // SCOPE-D4: every per-landlord branch of this query routes through
        // configsForLandlord(); the cross-tenant fleet branch is a separate,
        // explicitly named helper so a future contributor can't conflate the two.
        if ($landlordOption) {
            return $this->configsForLandlord((int) $landlordOption)
                ->pluck('landlord_id');
        }

        return $this->gatewayConfiguredFleet()
            ->pluck('payment_configurations.landlord_id');
    }

    private function configsForLandlord(int $landlordId)
    {
        if ($landlordId <= 0) {
            throw new \InvalidArgumentException('Reconciliation requires a positive landlord id.');
        }

        // Phase-85 RECON-STRIPE-1: match a landlord configured for EITHER gateway.
        return PaymentConfiguration::withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId)
            ->where(fn ($q) => $q
                ->where(fn ($p) => $p->where('paystack_enabled', true)->whereNotNull('paystack_secret_key'))
                ->orWhere(fn ($s) => $s->where('stripe_enabled', true)->whereNotNull('stripe_secret_key')));
    }

    // Intentional cross-tenant query — the daily cron sweeps every active
    // landlord with a payment gateway (Paystack OR Stripe) configured. Documented
    // so the next reader knows this is the one place the landlord_id filter is
    // omitted on purpose.
    private function gatewayConfiguredFleet()
    {
        return PaymentConfiguration::withoutGlobalScope('landlord')
            ->where(fn ($q) => $q
                ->where(fn ($p) => $p->where('paystack_enabled', true)->whereNotNull('paystack_secret_key'))
                ->orWhere(fn ($s) => $s->where('stripe_enabled', true)->whereNotNull('stripe_secret_key')))
            ->join('users', 'payment_configurations.landlord_id', '=', 'users.id')
            ->where('users.is_archived', false);
    }

    private function processLandlord(PaymentReconciliationService $service, int $landlordId): void
    {
        $config = PaymentConfiguration::withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId)
            ->first();

        if (! $config) {
            return;
        }

        // Phase-85 RECON-STRIPE-1: reconcile EVERY gateway the landlord has
        // configured, not just Paystack (the old code only ran reconcilePaystack,
        // so Stripe-configured landlords never got scheduled gateway recon).
        $gateways = [];
        if ($config->paystack_enabled && ! empty($config->paystack_secret_key)) {
            $gateways['paystack'] = fn () => $service->reconcilePaystack($landlordId, $this->from, $this->to);
        }
        if ($config->stripe_enabled && ! empty($config->stripe_secret_key)) {
            $gateways['stripe'] = fn () => $service->reconcileStripe($landlordId, $this->from, $this->to);
        }

        foreach ($gateways as $provider => $run) {
            $this->reconcileGateway($provider, $run, $landlordId);
        }
    }

    private function reconcileGateway(string $provider, \Closure $run, int $landlordId): void
    {
        try {
            $result = $run();

            if ($this->isDryRun) {
                $this->processed++;
                if ($result->hasDiscrepancies()) {
                    $this->withDiscrepancies++;
                }

                return;
            }

            $report = ReconciliationReport::storeFromResult($landlordId, $provider, $result, [$this->from, $this->to]);

            if ($report->hasDiscrepancies()) {
                $this->sendAlert($report, $landlordId);
                $this->withDiscrepancies++;
            }

            $this->processed++;
        } catch (\Throwable $e) {
            $this->errors++;

            Log::error('Reconciliation failed for landlord', [
                'landlord_id' => $landlordId,
                'gateway' => $provider,
                'error' => $e->getMessage(),
            ]);

            if (! $this->isDryRun) {
                ReconciliationReport::storeFailed($landlordId, $provider, $e->getMessage(), [$this->from, $this->to]);
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
