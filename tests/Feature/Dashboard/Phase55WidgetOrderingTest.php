<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Http\Controllers\DashboardPreferenceController;
use App\Models\LandlordDashboard;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-55 WIDGET-ORDERING-1/2/3 watchdog. PATCH /dashboards/preferences
 * upserts the landlord's slug='main_dashboard' LandlordDashboard row; the
 * dashboard service reads it on load and falls through to the canonical
 * default when no row exists.
 */
class Phase55WidgetOrderingTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_default_widget_order_returned_when_no_row_persisted(): void
    {
        $setup = $this->createLandlordWithFullSetup();

        $data = app(DashboardService::class)
            ->getLandlordDashboardData($setup['landlord'], new Request);

        $this->assertSame(
            DashboardPreferenceController::ALLOWED_WIDGETS,
            $data['widgetOrder'],
        );
    }

    public function test_patch_preferences_upserts_layout_row(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $newOrder = ['expiring-leases', 'recent-payments', 'recent-tickets'];

        $this->actingAs($setup['landlord'])
            ->patch(route('dashboards.preferences.update'), ['widget_order' => $newOrder])
            ->assertRedirect();

        $row = LandlordDashboard::query()
            ->where('landlord_id', $setup['landlord']->id)
            ->where('slug', DashboardPreferenceController::MAIN_DASHBOARD_SLUG)
            ->first();

        $this->assertNotNull($row);
        // Phase-74 CROSS-BUILDING-1 changed the persisted shape to
        // {widgets, scope}; assert via the canonical accessor (the test had
        // lagged this shape change and was failing pre-Phase-79).
        $this->assertSame($newOrder, DashboardPreferenceController::widgetsFrom($row->layout));
        $this->assertSame('active_building', DashboardPreferenceController::scopeFrom($row->layout));
    }

    public function test_persisted_order_surfaces_via_dashboard_payload(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $newOrder = ['recent-tickets', 'expiring-leases', 'recent-payments'];

        $this->actingAs($setup['landlord'])
            ->patch(route('dashboards.preferences.update'), ['widget_order' => $newOrder])
            ->assertRedirect();

        $data = app(DashboardService::class)
            ->getLandlordDashboardData($setup['landlord'], new Request);

        $this->assertSame($newOrder, $data['widgetOrder']);
    }

    public function test_rejects_unknown_widget_id(): void
    {
        $setup = $this->createLandlordWithFullSetup();

        $this->actingAs($setup['landlord'])
            ->patch(
                route('dashboards.preferences.update'),
                ['widget_order' => ['recent-payments', 'mystery-widget']]
            )
            ->assertSessionHasErrors('widget_order.1');
    }

    public function test_caretaker_cannot_update_preferences(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $setup['landlord']->id,
        ]);

        $this->actingAs($caretaker)
            ->patch(
                route('dashboards.preferences.update'),
                ['widget_order' => DashboardPreferenceController::ALLOWED_WIDGETS]
            )
            ->assertForbidden();
    }

    public function test_partial_layout_row_falls_through_to_full_default(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        LandlordDashboard::create([
            'landlord_id' => $setup['landlord']->id,
            'slug' => DashboardPreferenceController::MAIN_DASHBOARD_SLUG,
            'name' => 'Main',
            'layout' => ['recent-tickets'],
            'is_default' => false,
        ]);

        $data = app(DashboardService::class)
            ->getLandlordDashboardData($setup['landlord'], new Request);

        // Sanitised order: persisted ids first, missing canonical defaults appended.
        $this->assertSame(
            ['recent-tickets', 'recent-payments', 'expiring-leases'],
            $data['widgetOrder'],
        );
    }
}
