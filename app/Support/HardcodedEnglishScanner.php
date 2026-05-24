<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\Finder\Finder;

/**
 * Phase-43 LANG-COVERAGE-2: counts hardcoded English text nodes
 * inside Vue `<template>` blocks. Pairs with the shrink-only
 * baseline test that ratchets the count downward as templates
 * migrate to $t().
 *
 * Heuristic (intentionally conservative to keep false positives
 * low at the cost of missing some real cases):
 *   - Look at content between `<template>` and `</template>`.
 *   - Strip tag attributes, mustache expressions `{{ ... }}`,
 *     element tags, comments.
 *   - Remaining text is element body content. Lines containing a
 *     run of 4+ letters that is NOT already inside `$t()` / `t()`
 *     are counted as violations.
 *   - Lines tagged with `<!-- i18n-ignore -->` on the previous
 *     line are skipped.
 *
 * The scanner is deliberately PHP-side rather than an ESLint rule
 * because ESLint custom rules need flat-config plugin scaffolding
 * and the CI environment already runs PHPUnit. Phase 44 may
 * graft an ESLint rule on top for in-IDE feedback.
 */
final class HardcodedEnglishScanner
{
    /**
     * @return array{count: int, files: array<string, int>}
     */
    public function scan(string $dir): array
    {
        if (! is_dir($dir)) {
            return ['count' => 0, 'files' => []];
        }

        $finder = (new Finder)
            ->files()
            ->in($dir)
            ->name('*.vue');

        $total = 0;
        $perFile = [];

        foreach ($finder as $file) {
            $contents = $file->getContents();
            $violations = $this->scanContents($contents);
            if ($violations > 0) {
                $rel = str_replace($dir.DIRECTORY_SEPARATOR, '', $file->getPathname());
                $perFile[$rel] = $violations;
                $total += $violations;
            }
        }

        return ['count' => $total, 'files' => $perFile];
    }

    public function scanContents(string $contents): int
    {
        $templates = $this->extractTemplateBlocks($contents);
        $violations = 0;
        foreach ($templates as $template) {
            // Strip HTML comments first BUT preserve i18n-ignore
            // markers as a single-line placeholder so the
            // line-walker below still notices them.
            $template = preg_replace_callback(
                '/<!--(.*?)-->/s',
                static fn (array $m) => str_contains($m[1], 'i18n-ignore') ? '<!-- i18n-ignore -->' : '',
                $template,
            ) ?? $template;

            $lines = preg_split('/\r?\n/', $template);
            $skipNext = false;
            // Collapse same-line opt-outs first: a line that carries
            // both an i18n-ignore comment and English text counts as
            // a single line; we treat the ignore as scoping the
            // whole line.
            foreach ($lines as $rawLine) {
                if ($skipNext) {
                    $skipNext = false;

                    continue;
                }
                $line = trim($rawLine);
                if ($line === '') {
                    continue;
                }
                if (str_contains($line, 'i18n-ignore')) {
                    // Two valid placements: previous-line comment OR
                    // inline same-line. Either way, skip this line.
                    $skipNext = ! preg_match('/i18n-ignore.*?[A-Za-z]/', $line);

                    continue;
                }
                $stripped = $this->stripNoise($line);
                $stripped = trim($stripped);
                if ($stripped === '') {
                    continue;
                }
                if ($this->lineHasUnwrappedEnglish($stripped)) {
                    $violations++;
                }
            }
        }

        return $violations;
    }

    /**
     * @return array<int, string>
     */
    private function extractTemplateBlocks(string $contents): array
    {
        $out = [];
        if (preg_match_all('/<template[^>]*>(.*?)<\/template>/s', $contents, $m)) {
            foreach ($m[1] as $body) {
                $out[] = $body;
            }
        }

        return $out;
    }

    private function stripNoise(string $template): string
    {
        // Drop comments, mustache expressions, attribute clusters,
        // self-closing element tags. What's left is element body text.
        $patterns = [
            '/<!--.*?-->/s',                              // HTML comments
            '/\{\{.*?\}\}/s',                             // {{ expressions }}
            '/(?:^|\s)(?:v-\w+|:\w+|@\w+)="[^"]*"/',      // Vue directives (also when leading a wrapped attribute line)
            '/(?:^|\s)(?:class|style|id|ref|name|type|placeholder|aria-[a-z-]+|data-[a-z-]+|role|tabindex)="[^"]*"/i',
            '/<\/?[a-zA-Z][^>]*>/',                       // element tags themselves
        ];

        return preg_replace($patterns, ' ', $template) ?? '';
    }

    private function lineHasUnwrappedEnglish(string $line): bool
    {
        // Already wrapped in $t() / t() — caller handles translation.
        if (preg_match('/\b(?:\$t|i18n\.t|(?<![A-Za-z0-9_])t)\s*\(/', $line)) {
            return false;
        }

        // Marker comment opting this line out.
        if (str_contains($line, 'i18n-ignore')) {
            return false;
        }

        // Pure punctuation / numbers — fine.
        if (! preg_match('/[A-Za-z]{4,}/', $line)) {
            return false;
        }

        // Single tokens that look like code identifiers
        // (camelCase, snake_case, kebab-case, dotted paths) — fine.
        // Real prose has at least one space between letter clusters.
        if (! preg_match('/[A-Za-z]{2,}\s+[A-Za-z]/', $line)) {
            return false;
        }

        return true;
    }
}
