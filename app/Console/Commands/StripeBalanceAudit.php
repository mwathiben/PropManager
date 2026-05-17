<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OperationalIncident;
use App\Models\PaymentConfiguration;
use App\Models\Setting;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Phase-42 PAYOUT-AUDIT-1: twice-daily poll for Stripe Connect
 * payout failures. Iterates every landlord with a
 * stripe_connect_account_id, asks Stripe (in the landlord's
 * account context via Stripe-Account header) for payouts with
 * status=failed in the last 24h, and emits the
 * stripe_payout_failure_count{landlord_id} gauge. Any landlord
 * with > 0 failures gets an OperationalIncident sev3 logged so
 * Phase-32 on-call escalation fires.
 *
 * Webhook payout.failed gives real-time signal (PAYOUT-AUDIT-2);
 * this cron exists for completeness (in case the webhook is
 * dropped) + for the rolling gauge.
 */
class StripeBalanceAudit extends Command
{
    protected $signature = 'payouts:stripe-balance-audit';

    protected $description = 'Phase-42 PAYOUT-AUDIT-1: poll Stripe Connect for landlord payout failures and emit gauge.';

    public function handle(MetricsService $metrics): int
    {
        $secret = (string) (Setting::getSystem('stripe_secret_key') ?? '');
        if ($secret === '') {
            $this->info('Stripe not configured — skipping payout audit.');

            return self::SUCCESS;
        }

        $client = new StripeClient($secret);
        $cutoff = Carbon::now()->subDay()->getTimestamp();

        $configs = PaymentConfiguration::query()
            ->whereNotNull('stripe_connect_account_id')
            ->get(['id', 'landlord_id', 'stripe_connect_account_id']);

        $totalFailed = 0;
        $landlordsAudited = 0;

        foreach ($configs as $config) {
            $landlordsAudited++;
            $accountId = (string) $config->stripe_connect_account_id;

            try {
                $payouts = $client->payouts->all(
                    ['status' => 'failed', 'created' => ['gte' => $cutoff], 'limit' => 100],
                    ['stripe_account' => $accountId],
                );
            } catch (ApiErrorException $e) {
                Log::warning('payouts:stripe-balance-audit list failed', [
                    'landlord_id' => $config->landlord_id,
                    'account_id' => $accountId,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $count = count($payouts->data ?? []);
            $metrics->gauge('stripe_payout_failure_count', $count, [
                'landlord_id' => (string) $config->landlord_id,
            ]);

            if ($count > 0) {
                $totalFailed += $count;
                $this->openIncidentFor($config->landlord_id, $accountId, $count);
            }
        }

        $this->info(sprintf(
            'audited=%d landlords total_failed_payouts=%d',
            $landlordsAudited,
            $totalFailed,
        ));

        $metrics->gauge('stripe_payout_audit_landlord_count', $landlordsAudited);

        return self::SUCCESS;
    }

    private function openIncidentFor(int $landlordId, string $accountId, int $count): void
    {
        OperationalIncident::create([
            'title' => sprintf('Stripe payout failures detected — landlord_id=%d count=%d', $landlordId, $count),
            'severity' => OperationalIncident::SEV3,
            'status' => OperationalIncident::STATUS_OPEN,
            'opened_at' => Carbon::now(),
            'affected_services' => ['stripe', 'payouts', 'connect'],
            'summary' => sprintf(
                'payouts:stripe-balance-audit detected %d failed payouts in the last 24h on Stripe Connect account %s (landlord %d).',
                $count,
                $accountId,
                $landlordId,
            ),
        ]);
    }
}
