<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Phase-43 LANG-AUDIT-1: single loader for the merged i18n
 * bundle so lang:coverage, lang:audit, and lang:check all
 * compute the same view as
 * HandleInertiaRequests::getI18nBundle().
 */
final class LangBundleLoader
{
    /**
     * @return array<string, mixed>
     */
    public function load(string $locale): array
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

    /**
     * Returns the flat dotted key set of a bundle.
     *
     * @return array<int, string>
     */
    public function flatten(array $bundle, string $prefix = ''): array
    {
        $keys = [];
        foreach ($bundle as $k => $v) {
            $full = $prefix === '' ? (string) $k : $prefix.'.'.$k;
            if (is_array($v)) {
                foreach ($this->flatten($v, $full) as $nested) {
                    $keys[] = $nested;
                }
            } else {
                $keys[] = $full;
            }
        }

        return $keys;
    }
}
