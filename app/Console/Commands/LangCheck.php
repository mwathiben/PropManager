<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\LangBundleLoader;
use Illuminate\Console\Command;

/**
 * Phase-43 LANG-AUDIT-2: CI gate that fails the build on any
 * missing key in a pinned namespace. Pair with `lang:audit`
 * which surfaces loose-namespace drift as a soft signal.
 */
class LangCheck extends Command
{
    protected $signature = 'lang:check {--pinned-only}';

    protected $description = 'Phase-43 LANG-AUDIT-2: hard CI gate — exits 1 on missing keys in pinned namespaces.';

    public function handle(LangBundleLoader $loader): int
    {
        $pinned = (array) config('i18n.pinned_namespaces', []);
        $baselineLocale = (string) config('app.fallback_locale', 'en');
        $baselineBundle = $loader->load($baselineLocale);

        $available = $this->resolveTargetLocales($baselineLocale);
        $failures = [];

        foreach ($available as $locale) {
            $bundle = $loader->load($locale);
            foreach ($pinned as $namespace) {
                $baselineSection = $baselineBundle[$namespace] ?? null;
                if (! is_array($baselineSection)) {
                    continue;
                }
                $targetSection = $bundle[$namespace] ?? [];
                $missing = array_values(array_diff(
                    $loader->flatten($baselineSection, $namespace),
                    is_array($targetSection) ? $loader->flatten($targetSection, $namespace) : [],
                ));
                if ($missing !== []) {
                    $failures[$locale][$namespace] = $missing;
                }
            }
        }

        if ($failures === []) {
            $this->info(sprintf('lang:check — pinned namespaces (%s) parity-complete across locales.', implode(', ', $pinned)));

            return self::SUCCESS;
        }

        foreach ($failures as $locale => $perNamespace) {
            foreach ($perNamespace as $namespace => $missing) {
                $this->error(sprintf(
                    '[%s] %s missing %d keys: %s',
                    $locale,
                    $namespace,
                    count($missing),
                    implode(', ', array_slice($missing, 0, 10)).(count($missing) > 10 ? '...' : ''),
                ));
            }
        }

        return self::FAILURE;
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

        return array_values(array_filter((array) $list, static fn ($l) => $l !== $exclude));
    }
}
