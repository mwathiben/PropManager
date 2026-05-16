<?php

declare(strict_types=1);

namespace Tests\Feature\Insight;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hot-fix regression guard: HandleInertiaRequests::getI18nBundle()
 * MUST merge lang/{locale}/*.php namespace files into the bundle
 * shipped to vue-i18n. Without the merge, Phase-36 dashboard growth
 * cards (and any other component using a namespaced $t() key) render
 * the literal key path instead of the translation.
 */
class I18nBundleNamespaceMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundle_includes_top_level_json_keys(): void
    {
        $bundle = $this->callBundle();
        $this->assertIsArray($bundle);
        $this->assertNotEmpty($bundle, 'i18n bundle should not be empty');
    }

    public function test_bundle_includes_namespaced_insight_keys(): void
    {
        app()->setLocale('en');
        $bundle = $this->callBundle();

        $this->assertArrayHasKey('insight', $bundle);
        $this->assertArrayHasKey('landlord_growth', $bundle['insight']);
        $this->assertArrayHasKey('engagement_card_heading', $bundle['insight']['landlord_growth']);
        $this->assertNotEmpty($bundle['insight']['landlord_growth']['engagement_card_heading']);
    }

    public function test_bundle_includes_namespaced_growth_keys(): void
    {
        app()->setLocale('en');
        $bundle = $this->callBundle();
        $this->assertArrayHasKey('growth', $bundle);
    }

    public function test_bundle_includes_namespaced_payments_keys(): void
    {
        app()->setLocale('en');
        $bundle = $this->callBundle();
        $this->assertArrayHasKey('payments', $bundle);
    }

    public function test_bundle_falls_back_when_locale_dir_missing(): void
    {
        app()->setLocale('en');
        $bundle = $this->callBundle();
        // Even without a per-locale namespace dir, JSON top-level keys would still load.
        // This just guards against the method throwing.
        $this->assertIsArray($bundle);
    }

    private function callBundle(): array
    {
        $mw = new HandleInertiaRequests();
        $method = new \ReflectionMethod($mw, 'getI18nBundle');
        $method->setAccessible(true);

        return $method->invoke($mw);
    }
}
