<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use Tests\TestCase;

/**
 * Phase-66 ONBOARDING-TOUR-3 surface watchdog: PHPUnit cannot render
 * Vue, so this asserts the tour engine is wired end-to-end — the
 *
 * @floating-ui dependency, both components, the global mount, the nav
 * [data-tour] anchors, the service registry, the Inertia share, and the
 * i18n copy. Catches drift (a deleted mount, a renamed anchor) that the
 * behavioural backend tests can't see.
 */
class Phase66OnboardingTourSurfaceTest extends TestCase
{
    private function read(string $relative): string
    {
        $path = base_path($relative);
        $this->assertFileExists($path, "{$relative} should exist");

        return (string) file_get_contents($path);
    }

    public function test_floating_ui_dependency_is_declared(): void
    {
        $pkg = json_decode($this->read('package.json'), true);
        $this->assertArrayHasKey('@floating-ui/vue', $pkg['dependencies'] ?? []);
    }

    public function test_tour_components_exist(): void
    {
        $this->read('resources/js/Components/Tour/TourOverlay.vue');
        $this->read('resources/js/Components/Tour/TourTooltip.vue');
    }

    public function test_tour_overlay_is_mounted_with_anchors(): void
    {
        $layout = $this->read('resources/js/Layouts/AuthenticatedLayout.vue');
        $this->assertStringContainsString("import TourOverlay from '@/Components/Tour/TourOverlay.vue'", $layout);
        $this->assertStringContainsString('<TourOverlay', $layout);
        $this->assertStringContainsString(':data-tour="item.tour"', $layout);

        $anchors = [
            "tour: 'nav-dashboard'", "tour: 'nav-buildings'", "tour: 'nav-tenants'",
            "tour: 'nav-finances'", "tour: 'nav-tickets'", "tour: 'nav-tenant-finances'",
            "tour: 'nav-inbox'",
        ];
        foreach ($anchors as $anchor) {
            $this->assertStringContainsString($anchor, $layout, "Missing nav anchor {$anchor}");
        }
    }

    public function test_service_registry_and_inertia_share_present(): void
    {
        $service = $this->read('app/Services/Onboarding/TourService.php');
        foreach (['landlord-dashboard', 'caretaker-intro', 'tenant-intro'] as $tourKey) {
            $this->assertStringContainsString("'{$tourKey}'", $service, "Registry missing {$tourKey}");
        }

        $share = $this->read('app/Http/Middleware/HandleInertiaRequests.php');
        $this->assertStringContainsString('onboarding_tour', $share);
    }

    public function test_tour_copy_present_in_en_and_sw(): void
    {
        foreach (['en', 'sw'] as $locale) {
            $bundle = require base_path("lang/{$locale}/onboarding.php");
            $this->assertArrayHasKey('tour', $bundle, "[{$locale}] missing tour copy");
            $this->assertArrayHasKey('landlord-dashboard', $bundle['tour']);
            $this->assertArrayHasKey('nav', $bundle['tour']);
            $this->assertArrayHasKey('step_of', $bundle['tour']);
        }
    }
}
