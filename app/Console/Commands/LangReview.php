<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Phase-52 APPLY-WORKFLOW-2: surface every lang row whose value still
 * carries the [TODO:<locale>] marker so human reviewers can see the
 * queue at a glance.
 *
 * Filters:
 *   --locale=ar      only this locale
 *   --namespace=auth only this namespace
 *
 * Output is a table of (locale, namespace, dotted-key, current value).
 */
class LangReview extends Command
{
    protected $signature = 'lang:review {--locale=} {--namespace=}';

    protected $description = 'Phase-52 APPLY-WORKFLOW-2: list lang rows still flagged with [TODO:<locale>] markers.';

    public function handle(): int
    {
        $localeFilter = $this->option('locale') ? (string) $this->option('locale') : null;
        $namespaceFilter = $this->option('namespace') ? (string) $this->option('namespace') : null;

        $rows = [];
        $langPath = base_path('lang');
        if (! File::isDirectory($langPath)) {
            $this->error("lang/ directory not found at {$langPath}");

            return self::FAILURE;
        }

        foreach (File::directories($langPath) as $localeDir) {
            $locale = basename($localeDir);
            if ($localeFilter !== null && $locale !== $localeFilter) {
                continue;
            }

            foreach (File::files($localeDir) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $namespace = $file->getFilenameWithoutExtension();
                if ($namespaceFilter !== null && $namespace !== $namespaceFilter) {
                    continue;
                }
                $contents = require $file->getPathname();
                if (! is_array($contents)) {
                    continue;
                }
                foreach ($this->flat($contents) as $dotted => $value) {
                    if (! is_string($value)) {
                        continue;
                    }
                    // Matches both Phase-52 [TODO:locale] (colon) and
                    // Phase-44 [TODO-locale] (hyphen, hand-seeded) markers.
                    if (preg_match('/\[TODO[:\-][a-zA-Z-]+\]/', $value) === 1) {
                        $rows[] = [$locale, $namespace, $dotted, mb_substr($value, 0, 60)];
                    }
                }
            }
        }

        if ($rows === []) {
            $this->info('No [TODO:*] rows found.');

            return self::SUCCESS;
        }

        $this->table(['locale', 'namespace', 'key', 'value (truncated)'], $rows);
        $this->info(count($rows) . ' row(s) need human review.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function flat(array $source, string $prefix = ''): array
    {
        $out = [];
        foreach ($source as $k => $v) {
            $path = $prefix === '' ? (string) $k : "{$prefix}.{$k}";
            if (is_array($v)) {
                foreach ($this->flat($v, $path) as $kk => $vv) {
                    $out[$kk] = $vv;
                }

                continue;
            }
            $out[$path] = $v;
        }

        return $out;
    }
}
