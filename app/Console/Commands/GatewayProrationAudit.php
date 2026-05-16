<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SubscriptionChange;
use App\Services\MetricsService;
use App\Services\PaystackSubscriptionService;
use App\Services\Sre\AlertFiringRecorder;
use Illuminate\Console\Command;

/**
 * Phase-37 PWA-GATEWAY-3: nightly reconciliation of local
 * subscription_changes vs Paystack gateway state. Catches drift
 * for UPGRADE rows where SubscriptionService::changePlan wrote the
 * DB but the gateway call failed (gateway_response NULL or
 * success=false). Fires high_gateway_proration_drift (sev3) when
 * the unreconciled count exceeds the threshold.
 */
class GatewayProrationAudit extends Command
{
    protected $signature = 'gateway:proration-audit {--threshold=5}';

    protected $description = 'Phase-37 PWA-GATEWAY-3: reconcile upgrade SubscriptionChange rows with Paystack.';

    public function handle(
        PaystackSubscriptionService $paystack,
        AlertFiringRecorder $recorder,
        MetricsService $metrics,
    ): int {
        $threshold = max(1, (int) $this->option('threshold'));

        $drift = SubscriptionChange::query()
            ->where('change_type', SubscriptionChange::TYPE_UPGRADE)
            ->where('effective_at', '>=', now()->subDay())
            ->with('subscription:id,paystack_subscription_code')
            ->get()
            ->filter(function (SubscriptionChange $change) {
                $response = $change->gateway_response;
                if (! is_array($response)) {
                    return true;
                }

                return ! ($response['success'] ?? false);
            });

        $reconciled = 0;
        $stillDrifted = 0;
        $missingMetadata = 0;

        foreach ($drift as $change) {
            $code = $change->subscription?->paystack_subscription_code;
            if (! $code) {
                $missingMetadata++;

                continue;
            }
            $gateway = $paystack->syncFromGateway($code);
            $change->update(['gateway_response' => array_merge(
                ['reconciled_at' => now()->toIso8601String()],
                $gateway,
            )]);
            if ($gateway['success']) {
                $reconciled++;
            } else {
                $stillDrifted++;
            }
        }

        $totalUnreconciled = $stillDrifted + $missingMetadata;
        $metrics->gauge('subscription_proration_drift_count_24h', $totalUnreconciled);
        $metrics->gauge('subscription_proration_reconciled_count_24h', $reconciled);

        $this->info(sprintf(
            'Reconciled %d, still drifted %d, missing metadata %d (threshold %d).',
            $reconciled,
            $stillDrifted,
            $missingMetadata,
            $threshold,
        ));

        if ($totalUnreconciled > $threshold) {
            $recorder->record(
                alertKey: 'high_gateway_proration_drift',
                value: $totalUnreconciled,
                threshold: (float) $threshold,
                metadata: [
                    'still_drifted' => $stillDrifted,
                    'missing_metadata' => $missingMetadata,
                ],
            );
        } else {
            $recorder->resolve('high_gateway_proration_drift');
        }

        return self::SUCCESS;
    }
}
