<?php

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Regression coverage for two bugs found by the full-site Playwright sweep:
 *
 *  - GET /notifications/settings 500'd: NotificationsController::settings()
 *    still called $this->loadGlobalPreferences(), which the M2 decomposition
 *    moved onto NotificationSettingsService — the settings *page* route had
 *    no test, only the global-prefs JSON endpoint did.
 *  - GET /tenants/history and /tenants/search 404'd: the literal routes were
 *    shadowed by the unconstrained /tenants/{tenant} (show) param route, which
 *    matched first and missed on model binding. Fixed with whereNumber.
 */
class NotificationsAndTenantRoutesTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_notification_settings_page_renders_for_landlord(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();

        $this->actingAs($landlord)
            ->get(route('notifications.settings'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Notifications/Index')
                ->where('activeTab', 'settings')
                ->has('globalPreferences')
            );
    }

    public function test_tenants_history_route_is_not_shadowed_by_show(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();

        // Before the whereNumber fix this hit TenantController@show with
        // tenant="history" and 404'd on the model-binding miss.
        $this->actingAs($landlord)
            ->get(route('tenants.history'))
            ->assertOk();
    }

    public function test_tenants_search_route_is_not_shadowed_by_show(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();

        $this->actingAs($landlord)
            ->get(route('tenants.search'))
            ->assertOk();
    }

    public function test_numeric_tenant_id_still_routes_to_show(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant] = $this->createTenantWithActiveLease($landlord, $units->first());

        // whereNumber must not break the real {tenant} route for numeric ids.
        $this->actingAs($landlord)
            ->get(route('tenants.show', $tenant))
            ->assertOk();
    }
}
