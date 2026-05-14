<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase-21 DEFER-DPA-1: nightly audit detecting tenants with a minor
 * date of birth (dob within the last 18 years) whose parental consent
 * artefact + timestamp are NOT recorded.
 *
 * Kenya DPA Article 8 / Section 33 requires verifiable parental consent
 * before processing minor data. The UpdateTenantRequest validation +
 * KenyaDpaService::minorRequiresConsent() gate the data-entry path;
 * this drift audit catches escapes — pre-migration rows where dob was
 * later set via direct DB write, KYC import paths that bypass the
 * Form Request, or future code that calls $tenant->update() without
 * the dependent consent fields.
 *
 * Pattern mirrors Phase-19 INDEX-1 (latefees:audit-drift) and
 * Phase-18 DATA-2 (wallets:audit-balances). Schedule slot is 05:45
 * Africa/Nairobi — 5min after latefees:audit-drift so each command
 * has clean MetricsService::gauge writes (no race).
 *
 * Emits `tenant_minor_missing_consent_count{landlord_id}` Prometheus
 * gauge for the Phase-14 ops dashboards. FAILURE exit on any drift so
 * operator monitoring (CronWatch / Sentry cron) alerts immediately.
 */
class AuditMinorTenantConsent extends Command
{
    protected $signature = 'tenants:audit-minor-consent {--limit=200 : max rows to report}';

    protected $description = 'Phase-21 DEFER-DPA-1: detect minor tenants without parental consent (Kenya DPA Article 8).';

    public function handle(MetricsService $metrics): int
    {
        $eighteenYearsAgo = CarbonImmutable::today()->subYears(18)->toDateString();

        $rows = DB::table('users')
            ->where('role', 'tenant')
            ->whereNull('deleted_at')
            ->whereNotNull('dob')
            ->where('dob', '>', $eighteenYearsAgo)
            ->whereNull('parental_consent_provided_at')
            ->select('id', 'landlord_id', 'dob')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($rows->isEmpty()) {
            $this->info('tenants:audit-minor-consent: 0 minor tenants missing parental consent.');

            try {
                $metrics->gauge('tenant_minor_missing_consent_count', 0.0);
            } catch (\Throwable) {
            }

            return self::SUCCESS;
        }

        $this->warn("tenants:audit-minor-consent: {$rows->count()} minor tenants missing parental consent:");
        foreach ($rows as $row) {
            $this->warn(sprintf(
                '  user_id=%d landlord_id=%d dob=%s',
                $row->id,
                $row->landlord_id,
                $row->dob,
            ));
        }

        Log::channel(config('logging.schedule_channel', 'stack'))->warning(
            'tenants:audit-minor-consent detected drift',
            [
                'count' => $rows->count(),
                'cutoff_dob' => $eighteenYearsAgo,
                'sample' => $rows->take(10)->map(fn ($r) => (array) $r)->all(),
            ]
        );

        try {
            $metrics->gauge('tenant_minor_missing_consent_count', (float) $rows->count());

            $byLandlord = $rows->groupBy('landlord_id');
            foreach ($byLandlord as $landlordId => $bucket) {
                $metrics->gauge(
                    'tenant_minor_missing_consent_count',
                    (float) $bucket->count(),
                    ['landlord_id' => (string) $landlordId],
                );
            }
        } catch (\Throwable) {
        }

        return self::FAILURE;
    }
}
