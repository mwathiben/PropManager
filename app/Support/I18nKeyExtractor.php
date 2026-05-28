<?php

declare(strict_types=1);

namespace App\Support;

use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Phase-43 LANG-COVERAGE-1: walks a frontend source directory and
 * pulls out every i18n key referenced via vue-i18n's $t()/t().
 * Result is consumed by App\Console\Commands\LangCoverage which
 * asserts each key resolves against the merged Inertia bundle.
 *
 * Patterns recognised (single + double quoted strings only —
 * dynamic key construction at runtime is undetectable statically
 * and is treated as out-of-scope for the watchdog):
 *   $t('namespace.key')
 *   $t("namespace.key")
 *   t('namespace.key')
 *   t("namespace.key")
 *   i18n.t('namespace.key')
 *
 * File globs: *.vue, *.ts, *.tsx, *.js, *.jsx.
 */
final class I18nKeyExtractor
{
    /**
     * @return array<int, string> Deduplicated, sorted list of keys.
     */
    public function extractFromDirectory(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $finder = (new Finder)
            ->files()
            ->in($dir)
            ->name(['*.vue', '*.ts', '*.tsx', '*.js', '*.jsx']);

        $keys = [];
        foreach ($finder as $file) {
            assert($file instanceof SplFileInfo);
            $contents = $file->getContents();
            foreach ($this->extractFromString($contents) as $key) {
                $keys[$key] = true;
            }
        }

        $unique = array_keys($keys);
        sort($unique);

        return $unique;
    }

    /**
     * @return array<int, string>
     */
    public function extractFromString(string $contents): array
    {
        // The trailing `\s*[,)]` requirement filters dynamic key
        // construction such as `t('prefix.' + suffix)` — without it
        // the regex would capture 'prefix.' and report a spurious miss.
        $pattern = '/\b(?:\$t|i18n\.t|(?<![A-Za-z0-9_])t)\s*\(\s*([\'"])([A-Za-z0-9_.\-]+)\1\s*[,)]/u';
        $matches = [];
        preg_match_all($pattern, $contents, $matches);

        return array_values(array_unique($matches[2] ?? []));
    }
}
