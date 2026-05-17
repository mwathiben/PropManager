<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MetricsService;
use App\Support\LangBundleLoader;
use Illuminate\Console\Command;

/**
 * Phase-43 LANG-AUDIT-1: diffs each non-English locale's merged
 * bundle against the English baseline. Emits
 * i18n_missing_keys_count{namespace,locale} Prometheus gauge so
 * Grafana + on-call see drift in real time. Pinned namespaces
 * (config('i18n.pinned_namespaces')) trigger sev3 at threshold 0;
 * loose namespaces sev4 at threshold 10.
 *
 * PR-time enforcement lives in `lang:check` (LANG-AUDIT-2); this
 * cron is the runtime safety net.
 */
class LangAudit extends Command
{
    protected $signature = 'lang:audit {--json} {--locale=}';

    protected $description = 'Phase-43 LANG-AUDIT-1: diff every locale bundle against English, emit i18n_missing_keys_count gauge.';

    public function handle(LangBundleLoader $loader, MetricsService $metrics): int
    {
        $baselineLocale = (string) config('app.fallback_locale', 'en');
        $baselineBundle = $loader->load($baselineLocale);
        if ($baselineBundle === []) {
            $this->error("Baseline locale bundle is empty: {$baselineLocale}");

            return self::FAILURE;
        }

        $available = $this->resolveTargetLocales($baselineLocale);
        $jsonOut = (bool) $this->option('json');
        $report = [];

        foreach ($available as $locale) {
            $bundle = $loader->load($locale);
            $perNamespace = $this->diffByNamespace($baselineBundle, $bundle);

            foreach ($perNamespace as $namespace => $missing) {
                $count = count($missing);
                $metrics->gauge('i18n_missing_keys_count', $count, [
                    'namespace' => $namespace,
                    'locale' => $locale,
                ]);
                $report[$locale][$namespace] = [
                    'count' => $count,
                    'missing' => $missing,
                ];
            }
        }

        if ($jsonOut) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
        } else {
            $this->renderTable($report);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function diffByNamespace(array $baseline, array $target): array
    {
        $loader = app(LangBundleLoader::class);
        $perNamespace = [];

        foreach ($baseline as $namespace => $contents) {
            $baselineKeys = is_array($contents)
                ? $loader->flatten($contents, (string) $namespace)
                : [(string) $namespace];

            $targetSection = is_array($target) && array_key_exists($namespace, $target)
                ? $target[$namespace]
                : [];
            $targetKeys = is_array($targetSection)
                ? $loader->flatten($targetSection, (string) $namespace)
                : [(string) $namespace];

            $missing = array_values(array_diff($baselineKeys, $targetKeys));
            $perNamespace[(string) $namespace] = $missing;
        }

        return $perNamespace;
    }

    /**
     * @return array<int, string>
     */
    private function resolveTargetLocales(string $exclude): array
    {
        $configured = config('app.available_locales');
        if (is_array($configured)) {
            $list = array_keys($configured);
        } else {
            $list = array_filter(array_map(
                static fn (string $dir) => basename($dir),
                glob(base_path('lang/*'), GLOB_ONLYDIR) ?: []
            ));
        }

        if (! is_array($list) || $list === []) {
            $list = ['sw'];
        }

        $opt = (string) $this->option('locale');
        if ($opt !== '') {
            return [$opt];
        }

        return array_values(array_filter($list, static fn (string $l) => $l !== $exclude));
    }

    /**
     * @param  array<string, array<string, array{count: int, missing: array<int, string>}>>  $report
     */
    private function renderTable(array $report): void
    {
        $rows = [];
        foreach ($report as $locale => $perNamespace) {
            foreach ($perNamespace as $namespace => $entry) {
                if ($entry['count'] === 0) {
                    continue;
                }
                $rows[] = [$locale, $namespace, $entry['count']];
            }
        }
        if ($rows === []) {
            $this->info('lang:audit — no missing keys.');

            return;
        }

        $this->table(['locale', 'namespace', 'missing'], $rows);
    }
}
