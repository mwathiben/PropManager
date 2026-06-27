<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\I18n\TranslationCostTracker;
use App\Services\MetricsService;
use App\Services\WorkflowLogger;
use Illuminate\Console\Command;

/**
 * Phase-53 GAUGE-WIRING-3: emit i18n_translation_spend_usd_24h gauge
 * (total + per-locale). Scrapes TranslationCostTracker Cache keys
 * (i18n:translation:spend:daily:total:* + per-locale variants) and
 * emits Prometheus gauges so the Phase-52 sev3 $20/day budget alert
 * (alert-thresholds.md line 39) has a backing time-series.
 *
 * Every 15 minutes — granular enough that the alert window catches
 * runaway spend before the daily budget cap silently fails calls
 * over to the stub driver.
 */
class I18nSpendAudit extends Command
{
    protected $signature = 'i18n:spend-audit {--dry-run}';

    protected $description = 'Phase-53 GAUGE-WIRING-3: emit i18n_translation_spend_usd_24h gauge.';

    public function handle(
        MetricsService $metrics,
        TranslationCostTracker $tracker,
        WorkflowLogger $workflowLogger,
    ): int {
        $dryRun = (bool) $this->option('dry-run');

        $totalUsd = $tracker->currentDailySpend();
        $locales = $this->supportedLocales();
        $perLocale = [];
        foreach ($locales as $locale) {
            $perLocale[$locale] = $tracker->localeDailySpend($locale);
        }

        if (! $dryRun) {
            try {
                $metrics->gauge('i18n_translation_spend_usd_24h', $totalUsd);
                foreach ($perLocale as $locale => $usd) {
                    $metrics->gauge('i18n_translation_spend_usd_24h_by_locale', $usd, [
                        'locale' => $locale,
                    ]);
                }
            } catch (\Throwable) {
                // best-effort
            }
        }

        $this->info(sprintf(
            'i18n:spend-audit: total=$%.4f across %d locale(s)%s',
            $totalUsd,
            count($locales),
            $dryRun ? ' (dry-run)' : '',
        ));

        $workflowLogger->log(
            workflowName: 'i18n:spend-audit',
            action: 'completed',
            metadata: [
                'total_usd' => $totalUsd,
                'per_locale_usd' => $perLocale,
                'dry_run' => $dryRun,
            ],
        );

        return self::SUCCESS;
    }

    /**
     * Enumerate lang/ subdirectories. Resilient to new locales landing
     * in the repo without needing a config change.
     *
     * @return list<string>
     */
    private function supportedLocales(): array
    {
        $langPath = lang_path();
        if (! is_dir($langPath)) {
            return ['en'];
        }

        $locales = $this->scanLocaleDirectories($langPath);

        return $locales ?: ['en'];
    }

    /**
     * @return list<string>
     */
    private function scanLocaleDirectories(string $langPath): array
    {
        $locales = [];
        foreach (scandir($langPath) ?: [] as $entry) {
            if ($this->isSkippableEntry($entry)) {
                continue;
            }
            if (is_dir($langPath.DIRECTORY_SEPARATOR.$entry)) {
                $locales[] = $entry;
            }
        }

        return $locales;
    }

    private function isSkippableEntry(string $entry): bool
    {
        return $entry === '.' || $entry === '..' || $entry === 'vendor';
    }
}
