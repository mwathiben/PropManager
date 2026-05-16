<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ServiceSlo;
use Illuminate\Database\Seeder;

/**
 * Phase-32 SRE-BUDGET-1: seed the four SLO tiers documented in
 * docs/runbooks/slo.md so ErrorBudgetCalculator + slo:budget-audit
 * have rows to operate on from day one.
 */
class Phase32ServiceSloSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            [
                'service_key' => 'tenant_facing_web',
                'tier' => ServiceSlo::TIER_1,
                'window_days' => 30,
                'objective_pct' => 99.5,
                'good_indicator_metric' => 'http_request_count_ok',
                'bad_indicator_metric' => 'slo_budget_fast_burn',
                'description' => 'Inertia + API v1 tenant-facing web (Phase-14 OBSERV-5 Tier 1).',
            ],
            [
                'service_key' => 'payment_webhook_handlers',
                'tier' => ServiceSlo::TIER_2,
                'window_days' => 30,
                'objective_pct' => 99.9,
                'good_indicator_metric' => 'webhook_processed',
                'bad_indicator_metric' => 'dependency_down',
                'description' => 'Payment webhook receive-and-reconcile (Phase-14 OBSERV-5 Tier 2).',
            ],
            [
                'service_key' => 'background_jobs',
                'tier' => ServiceSlo::TIER_3,
                'window_days' => 30,
                'objective_pct' => 95.0,
                'good_indicator_metric' => 'queue_jobs_processed',
                'bad_indicator_metric' => 'queue_depth_high',
                'description' => 'Notifications, exports, scheduled tasks (Phase-14 OBSERV-5 Tier 3).',
            ],
            [
                'service_key' => 'compliance_tasks',
                'tier' => ServiceSlo::TIER_4,
                'window_days' => 7,
                'objective_pct' => 100.0,
                'good_indicator_metric' => 'compliance_tasks_succeeded',
                'bad_indicator_metric' => 'workflow_silent_failure',
                'description' => 'Same-day compliance crons (DPA retention, breach SLA).',
            ],
        ];

        foreach ($tiers as $tier) {
            ServiceSlo::updateOrCreate(
                ['service_key' => $tier['service_key']],
                $tier,
            );
        }
    }
}
