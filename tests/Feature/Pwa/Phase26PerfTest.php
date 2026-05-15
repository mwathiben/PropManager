<?php

declare(strict_types=1);

namespace Tests\Feature\Pwa;

use Tests\TestCase;

/**
 * Phase-26 PWA-PERF-1 / 2 / 3 watchdogs: loading="lazy" sweep across
 * the img tags shipped in Phase 1a-1c, per-chunk bundle-size budget,
 * runtime-caching strategy doc presence (overlaps with Phase 1b
 * Phase26ShellTest::test_pwa_runbook_documents_caching_strategies —
 * kept here too so PWA-PERF-3 has a dedicated finding-named test).
 */
class Phase26PerfTest extends TestCase
{
    /**
     * @return array<string, list<string>>
     */
    private static function imgPagesWithCount(): array
    {
        return [
            'resources/js/Pages/Dashboard.vue' => ['profile_photo_url'],
            'resources/js/Pages/InvoiceSettings/Edit.vue' => ['getLogoUrl'],
            'resources/js/Pages/InvoiceTemplates/Edit.vue' => ['getLogoUrl'],
            'resources/js/Pages/ReceiptTemplates/Edit.vue' => ['getLogoUrl', 'qr_code'],
            'resources/js/Pages/Settings/partials/BrandingTab.vue' => ['logoPreview'],
        ];
    }

    public function test_every_img_in_known_pages_carries_loading_lazy(): void
    {
        // PWA-PERF-1: regression gate. Every <img in the six known Vue
        // sites must carry loading="lazy". Greps each file for <img
        // tags and asserts each one has loading="lazy". A new <img
        // without loading=lazy in these pages fails CI immediately.
        foreach (self::imgPagesWithCount() as $file => $hints) {
            $path = base_path($file);
            $this->assertFileExists($path, "PWA-PERF-1: {$file} must exist (referenced by Phase-26 PRD).");

            $content = (string) file_get_contents($path);
            preg_match_all('/<img\b[^>]*>/', $content, $matches);

            $this->assertNotEmpty(
                $matches[0],
                "PWA-PERF-1: expected at least one <img tag in {$file} (see PRD evidence).",
            );

            foreach ($matches[0] as $imgTag) {
                $this->assertStringContainsString(
                    'loading="lazy"',
                    $imgTag,
                    "PWA-PERF-1: <img in {$file} must carry loading=\"lazy\". Tag: {$imgTag}",
                );
            }
        }
    }

    public function test_bundle_size_script_declares_per_chunk_budgets(): void
    {
        $script = (string) file_get_contents(base_path('scripts/check-bundle-size.mjs'));

        $this->assertStringContainsString(
            'PER_CHUNK_BUDGETS',
            $script,
            'PWA-PERF-2: check-bundle-size.mjs must declare PER_CHUNK_BUDGETS — the named-chunk gate that catches "vendor doubled while leaflet halved" regressions invisible to total+largest.',
        );

        foreach (['vue-core', 'vendor', 'leaflet', 'app'] as $chunk) {
            $this->assertStringContainsString(
                "name: '{$chunk}'",
                $script,
                "PWA-PERF-2: per-chunk budget table must include '{$chunk}'.",
            );
        }
    }

    public function test_bundle_size_script_is_referenced_in_ci(): void
    {
        $workflow = (string) file_get_contents(base_path('.github/workflows/ci.yml'));
        $this->assertStringContainsString(
            'check-bundle-size.mjs',
            $workflow,
            'PWA-PERF-2: .github/workflows/ci.yml must run scripts/check-bundle-size.mjs — otherwise the budget is documented but ungated.',
        );
    }

    public function test_pwa_runbook_documents_runtime_caching_strategies(): void
    {
        // PWA-PERF-3: belt-and-braces with Phase26ShellTest.
        $content = (string) file_get_contents(base_path('docs/runbooks/pwa.md'));
        $this->assertStringContainsString(
            'Runtime caching contract',
            $content,
            'PWA-PERF-3: pwa.md must include a "Runtime caching contract" section listing each route family + its strategy.',
        );
        foreach (['CacheFirst', 'NetworkFirst', 'StaleWhileRevalidate', 'NetworkOnly'] as $strategy) {
            $this->assertStringContainsString(
                $strategy,
                $content,
                "PWA-PERF-3: pwa.md must mention the {$strategy} strategy.",
            );
        }
    }
}
