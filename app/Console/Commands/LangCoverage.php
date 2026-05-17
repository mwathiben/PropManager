<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\I18nKeyExtractor;
use Illuminate\Console\Command;

/**
 * Phase-43 LANG-COVERAGE-1: validates that every i18n key
 * referenced from the Vue frontend resolves against the merged
 * en bundle the Inertia layer ships to the client. Mirrors
 * HandleInertiaRequests::getI18nBundle() exactly so test +
 * production stay in lock step.
 *
 * Exit codes:
 *   0 — every $t() call resolves.
 *   1 — at least one key missing (printed to stderr-ish).
 */
class LangCoverage extends Command
{
    protected $signature = 'lang:coverage {--locale=en} {--dir=} {--json}';

    protected $description = 'Phase-43 LANG-COVERAGE-1: assert every $t() call in resources/js/ resolves against the merged lang bundle.';

    public function handle(I18nKeyExtractor $extractor): int
    {
        $locale = (string) $this->option('locale');
        $dir = (string) ($this->option('dir') ?: resource_path('js'));
        $jsonOut = (bool) $this->option('json');

        $bundle = $this->loadBundle($locale);
        if ($bundle === []) {
            $this->error("No lang bundle found for locale={$locale}");

            return self::FAILURE;
        }

        $keys = $extractor->extractFromDirectory($dir);
        $missing = [];
        foreach ($keys as $key) {
            if (! $this->bundleHas($bundle, $key)) {
                $missing[] = $key;
            }
        }

        if ($jsonOut) {
            $this->line(json_encode([
                'locale' => $locale,
                'directory' => $dir,
                'total_keys' => count($keys),
                'missing_count' => count($missing),
                'missing' => $missing,
            ], JSON_PRETTY_PRINT));
        } else {
            $this->info(sprintf(
                'lang:coverage locale=%s scanned=%d missing=%d',
                $locale,
                count($keys),
                count($missing),
            ));
            foreach ($missing as $key) {
                $this->warn("  missing: {$key}");
            }
        }

        return count($missing) === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Mirrors HandleInertiaRequests::getI18nBundle().
     */
    private function loadBundle(string $locale): array
    {
        $bundle = [];

        $jsonPath = base_path("lang/{$locale}.json");
        if (is_file($jsonPath)) {
            $bundle = json_decode((string) file_get_contents($jsonPath), true) ?: [];
        }

        $namespaceDir = base_path("lang/{$locale}");
        if (is_dir($namespaceDir)) {
            foreach (glob($namespaceDir.'/*.php') ?: [] as $file) {
                $namespace = basename($file, '.php');
                $bundle[$namespace] = require $file;
            }
        }

        return $bundle;
    }

    private function bundleHas(array $bundle, string $key): bool
    {
        $cursor = $bundle;
        foreach (explode('.', $key) as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return false;
            }
            $cursor = $cursor[$segment];
        }

        return true;
    }
}
