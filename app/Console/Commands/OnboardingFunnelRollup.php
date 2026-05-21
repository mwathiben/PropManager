<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MetricsService;
use App\Services\Onboarding\InvitationFunnelService;
use App\Services\Onboarding\OnboardingFunnelService;
use App\Services\Sre\AlertFiringRecorder;
use App\Services\WorkflowLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Phase-77 FUNNEL-3 / INVITE-FUNNEL-3: daily onboarding + invite funnel gauges
 * so product/ops can watch completion rate + drop-off + invite conversion over
 * time. Fires onboarding_completion_low (sev4) when a role with a meaningful
 * sample drops below the configured completion rate.
 *
 * Scheduled daily 04:55 Africa/Nairobi.
 */
class OnboardingFunnelRollup extends Command
{
    protected $signature = 'onboarding:funnel-rollup';

    protected $description = 'Phase-77 FUNNEL-3: emit onboarding step-funnel + invite-funnel gauges + low-completion alert.';

    private const MIN_SAMPLE = 10;

    public function handle(
        OnboardingFunnelService $funnel,
        InvitationFunnelService $invites,
        MetricsService $metrics,
        AlertFiringRecorder $alerts,
        WorkflowLogger $workflowLogger,
    ): int {
        $threshold = (float) config('onboarding.completion_rate_alert_pct', 40);
        $lowRoles = [];

        try {
            foreach ($funnel->all() as $role => $data) {
                $metrics->gauge('onboarding_completion_rate', (float) $data['completion_rate'], ['role' => $role]);
                $metrics->gauge('onboarding_active_sessions', (float) $data['active'], ['role' => $role]);
                $metrics->gauge('onboarding_dropoff_step', (float) ($data['drop_off_step'] ?? 0), ['role' => $role]);

                if ($data['total'] >= self::MIN_SAMPLE && $data['completion_rate'] < $threshold) {
                    $lowRoles[$role] = $data['completion_rate'];
                }
            }

            $invite = $invites->platform();
            $metrics->gauge('invitation_acceptance_rate', (float) $invite['acceptance_rate']);
            $metrics->gauge('invitations_pending_count', (float) $invite['pending']);

            if ($lowRoles !== []) {
                $alerts->record('onboarding_completion_low', (float) count($lowRoles), 1.0, [
                    'roles' => $lowRoles,
                    'threshold_pct' => $threshold,
                ]);
            } else {
                $alerts->resolve('onboarding_completion_low');
            }
        } catch (\Throwable $e) {
            Log::warning('onboarding:funnel-rollup emit failed', ['error' => $e->getMessage()]);
        }

        $this->info('onboarding:funnel-rollup: gauges emitted'.($lowRoles !== [] ? ' (low: '.implode(',', array_keys($lowRoles)).')' : ''));

        $workflowLogger->log(
            workflowName: 'onboarding:funnel-rollup',
            action: 'completed',
            metadata: ['low_roles' => array_keys($lowRoles)],
        );

        return self::SUCCESS;
    }
}
