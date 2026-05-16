<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MetricsService;
use App\Services\Sre\AlertFiringRecorder;
use Illuminate\Console\Command;

/**
 * Phase-38 DEFER-BUILD-CI-3: bundle freshness audit. Detects when
 * the current public/build/manifest.json is older than the newest
 * commit touching resources/js/. Catches the dev-time drift where
 * `php artisan serve` is running against a stale bundle from days
 * ago, OR when a developer pushed FE changes but forgot to rebuild
 * + commit the new public/build/ artifacts (if you don't commit
 * the build, this fires nonstop — set BUNDLE_AUDIT_DISABLED=1).
 *
 * Emits `bundle_age_hours_since_last_fe_commit` gauge. Fires
 * `stale_bundle_warning` sev4 when gap > threshold (default 24h).
 */
class BundleFreshnessAudit extends Command
{
    protected $signature = 'bundle:freshness-audit {--threshold=24}';

    protected $description = 'Phase-38 DEFER-BUILD-CI-3: alert when the Vite bundle is older than the last FE commit.';

    public function handle(MetricsService $metrics, AlertFiringRecorder $recorder): int
    {
        if (env('BUNDLE_AUDIT_DISABLED', false)) {
            $this->line('BUNDLE_AUDIT_DISABLED=1 — skipping.');

            return self::SUCCESS;
        }

        $manifestPath = public_path('build/manifest.json');
        if (! file_exists($manifestPath)) {
            $this->warn('public/build/manifest.json missing — run `npm run build`.');
            $recorder->record(
                alertKey: 'stale_bundle_warning',
                value: 9999,
                threshold: (float) $this->option('threshold'),
                metadata: ['reason' => 'manifest_missing'],
            );

            return self::SUCCESS;
        }

        $bundleMtime = filemtime($manifestPath);
        $latestFeCommit = $this->latestFeCommitEpoch();

        if ($latestFeCommit === null) {
            $this->info('No git history available — skipping freshness check.');

            return self::SUCCESS;
        }

        $ageHours = max(0, round(($latestFeCommit - $bundleMtime) / 3600.0, 2));
        $metrics->gauge('bundle_age_hours_since_last_fe_commit', $ageHours);

        $threshold = (float) $this->option('threshold');
        $this->line(sprintf(
            'Bundle %s; latest FE commit %s; age %s hours (threshold %s).',
            date('Y-m-d H:i:s', $bundleMtime),
            date('Y-m-d H:i:s', $latestFeCommit),
            $ageHours,
            $threshold,
        ));

        if ($ageHours > $threshold) {
            $recorder->record(
                alertKey: 'stale_bundle_warning',
                value: $ageHours,
                threshold: $threshold,
                metadata: [
                    'bundle_mtime' => date('c', $bundleMtime),
                    'latest_fe_commit' => date('c', $latestFeCommit),
                ],
            );
        } else {
            $recorder->resolve('stale_bundle_warning');
        }

        return self::SUCCESS;
    }

    private function latestFeCommitEpoch(): ?int
    {
        $output = [];
        $exitCode = 0;
        exec(
            'git log -1 --format=%ct -- resources/js/ vite.config.js package.json 2>&1',
            $output,
            $exitCode,
        );
        if ($exitCode !== 0 || empty($output)) {
            return null;
        }
        $epoch = (int) trim($output[0]);

        return $epoch > 0 ? $epoch : null;
    }
}
