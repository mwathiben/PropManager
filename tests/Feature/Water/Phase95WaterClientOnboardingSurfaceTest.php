<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Onboarding\OnboardingFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase-95 WATER-CLIENT-ONBOARDING surface guard: routes, onboarding flow, the
 * role blast-radius edits, lang parity.
 */
class Phase95WaterClientOnboardingSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_routes_registered(): void
    {
        $this->assertTrue(Route::has('water-invite.show'));
        $this->assertTrue(Route::has('water-invite.accept'));
        $this->assertTrue(Route::has('water-client-invitations.store'));
    }

    public function test_onboarding_flow_for_water_client(): void
    {
        $flow = OnboardingFlow::forRole('water_client');
        $this->assertSame([1, 2, 3], $flow->allSteps());
        $this->assertSame(3, $flow->lastStep());
    }

    public function test_dashboard_has_water_client_arm(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/DashboardController.php'));
        $this->assertStringContainsString("'water_client' => \$this->waterClientDashboard()", $src);
    }

    public function test_nav_handles_water_client(): void
    {
        $src = file_get_contents(resource_path('js/Layouts/AuthenticatedLayout.vue'));
        $this->assertStringContainsString("role === 'water_client'", $src);
    }

    public function test_role_label_present(): void
    {
        foreach (['en', 'ar', 'sw'] as $locale) {
            $json = json_decode(file_get_contents(base_path("lang/{$locale}.json")), true);
            $this->assertArrayHasKey('water_client', $json['role'] ?? [], "{$locale} missing role.water_client");
        }
    }

    public function test_lang_parity(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            $water = require base_path("lang/{$locale}/water.php");
            $this->assertArrayHasKey('clients', $water['tabs'] ?? []);
            foreach (['invite', 'accept_title', 'invite_welcome'] as $key) {
                $this->assertArrayHasKey($key, $water['clients'] ?? [], "{$locale} missing water.clients.{$key}");
            }
            $this->assertArrayHasKey('client_dash', $water, "{$locale} missing water.client_dash");
            $this->assertArrayHasKey('client_onboarding', $water, "{$locale} missing water.client_onboarding");
        }
    }
}
