<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PaymentConfiguration;
use App\Services\MetricsService;
use App\Services\Reconciliation\PaymentReconciliationService;
use App\Services\Sre\AlertFiringRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Phase-40 GATEWAY-RECONCILE-2: gateway-agnostic daily reconciliation
 * cron. Replaces (parallels) Phase-30 reconciliation:run-daily — that
 * command still ships for Paystack-only callers; this one iterates
 * BOTH gateways for each landlord and reports per-gateway drift.
 *
 * Fires gateway_drift sev3 (config/alerts.php) when any gateway's
 * drift_count exceeds the threshold.
 */
class PaymentsGatewayReconcile extends Command
{
    private const DRIFT_THRESHOLD = 5;

    protected $signature = 'payments:gateway-reconcile {--gateway=} {--landlord=}';

    protected $description = 'Phase-40 GATEWAY-RECONCILE-2: reconcile Paystack + Stripe ledgers against local payments per landlord.';

    /** @var array<string, int> */
    private array $perGatewayDrift = ['paystack' => 0, 'stripe' => 0];

    /** @var array<string, int> */
    private array $perGatewayLandlords = ['paystack' => 0, 'stripe' => 0];

    private CarbonImmutable $from;

    private CarbonImmutable $to;

    public function handle(
        PaymentReconciliationService $service,
        MetricsService $metrics,
        AlertFiringRecorder $recorder,
    ): int {
        $gatewayFilter = $this->option('gateway');
        $landlordFilter = $this->option('landlord');

        $this->from = CarbonImmutable::now()->subDay()->startOfDay();
        $this->to = CarbonImmutable::now()->subDay()->endOfDay();

        $configs = PaymentConfiguration::query()
            ->when($landlordFilter, fn ($q) => $q->where('landlord_id', (int) $landlordFilter))
            ->get();

        foreach ($configs as $config) {
            foreach ($this->resolveGateways($config, $gatewayFilter) as $gateway) {
                $this->reconcileGateway($service, $metrics, $config, $gateway);
            }
        }

        $this->reportAndAlert($metrics, $recorder);

        return self::SUCCESS;
    }

    /**
     * Determine which gateways to run for this config based on the filter and available credentials.
     */
    private function resolveGateways(PaymentConfiguration $config, ?string $gatewayFilter): array
    {
        $gateways = [];

        if ($this->gatewayAllowed('paystack', $gatewayFilter) && $config->hasPaystackConfig()) {
            $gateways[] = 'paystack';
        }

        if ($this->gatewayAllowed('stripe', $gatewayFilter) && $config->hasStripeConfig()) {
            $gateways[] = 'stripe';
        }

        return $gateways;
    }

    private function gatewayAllowed(string $gateway, ?string $filter): bool
    {
        return $filter === null || $filter === $gateway;
    }

    /**
     * Run reconciliation for one gateway + landlord and accumulate drift counters.
     */
    private function reconcileGateway(
        PaymentReconciliationService $service,
        MetricsService $metrics,
        PaymentConfiguration $config,
        string $gateway,
    ): void {
        try {
            $result = $service->reconcile($gateway, (int) $config->landlord_id, $this->from, $this->to);
            $driftCount = count($result->discrepancies);
            $this->perGatewayDrift[$gateway] += $driftCount;
            $this->perGatewayLandlords[$gateway]++;

            $metrics->gauge('gateway_reconcile_drift_count', $driftCount, [
                'gateway' => $gateway,
                'landlord_id' => (string) $config->landlord_id,
            ]);
        } catch (\Throwable $e) {
            $this->error(sprintf(
                'reconcile gateway=%s landlord=%d failed: %s',
                $gateway,
                $config->landlord_id,
                $e->getMessage(),
            ));
        }
    }

    /**
     * Emit per-gateway metrics, log summary lines, fire/resolve the drift alert.
     */
    private function reportAndAlert(MetricsService $metrics, AlertFiringRecorder $recorder): void
    {
        foreach ($this->perGatewayDrift as $gateway => $totalDrift) {
            $metrics->gauge('gateway_reconcile_drift_total', $totalDrift, ['gateway' => $gateway]);
            $this->line(sprintf(
                'gateway=%s landlords=%d total_drift=%d',
                $gateway,
                $this->perGatewayLandlords[$gateway],
                $totalDrift,
            ));

            if ($this->perGatewayLandlords[$gateway] > 0 && $totalDrift > self::DRIFT_THRESHOLD) {
                $recorder->record(
                    alertKey: 'gateway_drift',
                    value: (float) $totalDrift,
                    threshold: (float) self::DRIFT_THRESHOLD,
                    metadata: [
                        'gateway' => $gateway,
                        'landlords_audited' => $this->perGatewayLandlords[$gateway],
                    ],
                );
            }
        }

        if (array_sum($this->perGatewayDrift) <= self::DRIFT_THRESHOLD) {
            $recorder->resolve('gateway_drift');
        }
    }
}
