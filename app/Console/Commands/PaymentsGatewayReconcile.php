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

    public function handle(
        PaymentReconciliationService $service,
        MetricsService $metrics,
        AlertFiringRecorder $recorder,
    ): int {
        $gatewayFilter = $this->option('gateway');
        $landlordFilter = $this->option('landlord');

        $configs = PaymentConfiguration::query()
            ->when($landlordFilter, fn ($q) => $q->where('landlord_id', (int) $landlordFilter))
            ->get();

        $from = CarbonImmutable::now()->subDay()->startOfDay();
        $to = CarbonImmutable::now()->subDay()->endOfDay();

        $perGatewayDrift = ['paystack' => 0, 'stripe' => 0];
        $perGatewayLandlords = ['paystack' => 0, 'stripe' => 0];

        foreach ($configs as $config) {
            $gateways = [];
            if (($gatewayFilter === null || $gatewayFilter === 'paystack') && $config->hasPaystackConfig()) {
                $gateways[] = 'paystack';
            }
            if (($gatewayFilter === null || $gatewayFilter === 'stripe') && $config->hasStripeConfig()) {
                $gateways[] = 'stripe';
            }

            foreach ($gateways as $gateway) {
                try {
                    $result = $service->reconcile($gateway, (int) $config->landlord_id, $from, $to);
                    $driftCount = count($result->discrepancies);
                    $perGatewayDrift[$gateway] += $driftCount;
                    $perGatewayLandlords[$gateway]++;

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
        }

        foreach ($perGatewayDrift as $gateway => $totalDrift) {
            $metrics->gauge('gateway_reconcile_drift_total', $totalDrift, ['gateway' => $gateway]);
            $this->line(sprintf(
                'gateway=%s landlords=%d total_drift=%d',
                $gateway,
                $perGatewayLandlords[$gateway],
                $totalDrift,
            ));

            if ($perGatewayLandlords[$gateway] > 0 && $totalDrift > self::DRIFT_THRESHOLD) {
                $recorder->record(
                    alertKey: 'gateway_drift',
                    value: (float) $totalDrift,
                    threshold: (float) self::DRIFT_THRESHOLD,
                    metadata: [
                        'gateway' => $gateway,
                        'landlords_audited' => $perGatewayLandlords[$gateway],
                    ],
                );
            }
        }

        $totalDrift = array_sum($perGatewayDrift);
        if ($totalDrift <= self::DRIFT_THRESHOLD) {
            $recorder->resolve('gateway_drift');
        }

        return self::SUCCESS;
    }
}
