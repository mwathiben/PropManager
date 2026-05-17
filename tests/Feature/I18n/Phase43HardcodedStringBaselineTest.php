<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Support\HardcodedEnglishScanner;
use Tests\TestCase;

/**
 * Phase-43 LANG-COVERAGE-2: shrink-only baseline ratcheting the
 * count of hardcoded English text nodes inside Vue `<template>`
 * blocks. The Phase-22 PERF-NPLUS1-1 NPlusOneBaseline pattern —
 * existing literals are technical debt to migrate incrementally,
 * new code must use $t().
 *
 * To migrate a chunk: wrap the literals in $t(), ratchet this
 * baseline downward, commit. Never raise the baseline.
 */
class Phase43HardcodedStringBaselineTest extends TestCase
{
    /**
     * Initial baseline computed 2026-05-17 against
     * resources/js/. Lowering the constant requires the
     * scanner to confirm the new floor.
     */
    private const BASELINE = 3263;

    public function test_hardcoded_english_count_does_not_grow_beyond_baseline(): void
    {
        $scanner = new HardcodedEnglishScanner();
        $result = $scanner->scan(resource_path('js'));

        $this->assertLessThanOrEqual(
            self::BASELINE,
            $result['count'],
            sprintf(
                "Hardcoded English count grew above the baseline of %d (saw %d).\n".
                "Wrap new text in \$t() OR migrate an existing literal and ratchet the baseline down.\n".
                'Top offenders: %s',
                self::BASELINE,
                $result['count'],
                $this->formatTopOffenders($result['files']),
            ),
        );
    }

    public function test_scanner_recognises_unwrapped_english(): void
    {
        $scanner = new HardcodedEnglishScanner();
        $template = '<template><p>Please confirm your password.</p></template>';
        $this->assertSame(1, $scanner->scanContents($template));
    }

    public function test_scanner_ignores_wrapped_t_call(): void
    {
        $scanner = new HardcodedEnglishScanner();
        $template = '<template><p>{{ $t("auth.login.title") }}</p></template>';
        $this->assertSame(0, $scanner->scanContents($template));
    }

    public function test_scanner_ignores_i18n_ignore_comment(): void
    {
        $scanner = new HardcodedEnglishScanner();
        $template = "<template><p><!-- i18n-ignore -->Brand name PropManager</p></template>";
        $this->assertSame(0, $scanner->scanContents($template));
    }

    /**
     * @param  array<string, int>  $files
     */
    private function formatTopOffenders(array $files): string
    {
        arsort($files);
        $top = array_slice($files, 0, 5, true);
        $lines = [];
        foreach ($top as $file => $count) {
            $lines[] = "  {$count}  {$file}";
        }

        return PHP_EOL.implode(PHP_EOL, $lines);
    }
}
