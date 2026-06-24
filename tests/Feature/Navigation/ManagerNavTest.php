<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use Tests\TestCase;

/**
 * Slice-2: managers had no sidebar nav branch (Phase-1/2a gap) — they fell
 * through to the empty default. This guards that managers get the operational
 * nav + the manager-only Management section (Agreements + Owners), and that the
 * nav copy resolves in every locale. The nav is computed client-side, so the
 * branch is asserted on source; the i18n keys on the shared bundles.
 */
class ManagerNavTest extends TestCase
{
    private function layout(): string
    {
        return (string) file_get_contents(resource_path('js/Layouts/AuthenticatedLayout.vue'));
    }

    public function test_manager_shares_the_operational_nav_branch(): void
    {
        $source = $this->layout();

        $this->assertStringContainsString("role === 'landlord' || role === 'manager'", $source);
    }

    public function test_manager_nav_exposes_agreements_and_owners(): void
    {
        $source = $this->layout();

        $this->assertStringContainsString("role === 'manager' ?", $source);
        $this->assertStringContainsString("route('agreements.index')", $source);
        $this->assertStringContainsString("route('owners.index')", $source);
    }

    /**
     * @return list<array{string}>
     */
    public static function locales(): array
    {
        return [['en'], ['sw'], ['ar']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('locales')]
    public function test_management_nav_keys_present_in_locale(string $locale): void
    {
        $bundle = json_decode((string) file_get_contents(lang_path("{$locale}.json")), true);
        $nav = $bundle['nav'] ?? [];

        $this->assertArrayHasKey('management_section', $nav, "nav.management_section missing in {$locale}");
        $this->assertArrayHasKey('agreements', $nav, "nav.agreements missing in {$locale}");
        $this->assertArrayHasKey('owners', $nav, "nav.owners missing in {$locale}");
    }
}
