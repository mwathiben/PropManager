<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-54 MAINTENANCE-DEPTH-2 surface watchdog. Locks each category's
 * closure into a single assertion that a future refactor cannot
 * silently regress. Per-category behavioural tests live in the
 * category-named sibling files; this class is the cross-category
 * presence map.
 */
class Phase54MaintenanceDepth2SurfaceTest extends TestCase
{
    use RefreshDatabase;

    // -- VENDOR-NOTIFICATIONS ----------------------------------------------

    public function test_notify_vendor_on_assignment_listener_exists(): void
    {
        $this->assertTrue(class_exists(\App\Listeners\NotifyVendorOnAssignment::class));
        $this->assertContains(
            'Illuminate\Contracts\Queue\ShouldQueue',
            class_implements(\App\Listeners\NotifyVendorOnAssignment::class) ?: [],
        );
    }

    public function test_vendor_assignment_mailable_exists(): void
    {
        $this->assertTrue(class_exists(\App\Mail\VendorAssignmentMailable::class));
    }

    public function test_maintenance_lang_namespace_present_in_all_locales(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            $this->assertFileExists(base_path("lang/{$locale}/maintenance.php"));
        }
    }

    // -- SLA-LANDLORD-UI ---------------------------------------------------

    public function test_sla_routes_registered(): void
    {
        foreach (['sla.index', 'sla.store', 'sla.update', 'sla.destroy'] as $name) {
            $this->assertTrue(Route::has($name), "Route {$name} missing");
        }
    }

    public function test_sla_definition_policy_registered(): void
    {
        $gate = app(\Illuminate\Contracts\Auth\Access\Gate::class);
        $this->assertSame(
            \App\Policies\SlaDefinitionPolicy::class,
            $gate->getPolicyFor(\App\Models\SlaDefinition::class)::class,
        );
    }

    // -- PARTS-REORDER -----------------------------------------------------

    public function test_draft_purchase_orders_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('draft_purchase_orders'));
        $this->assertTrue(Schema::hasTable('draft_purchase_order_lines'));
    }

    public function test_parts_reorder_suggest_scheduled(): void
    {
        $events = collect(\Illuminate\Support\Facades\Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'parts:reorder-suggest'));

        $this->assertNotNull($entry);
        $this->assertSame('45 6 * * *', $entry->expression);
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    public function test_parts_purchase_orders_routes_registered(): void
    {
        foreach (['parts.purchase-orders.index', 'parts.purchase-orders.confirm', 'parts.purchase-orders.cancel'] as $name) {
            $this->assertTrue(Route::has($name), "Route {$name} missing");
        }
    }

    // -- COST-UI -----------------------------------------------------------

    public function test_ticket_cost_route_registered(): void
    {
        $this->assertTrue(Route::has('tickets.costs.store'));
    }

    public function test_ticket_show_vue_has_cost_section(): void
    {
        $body = (string) file_get_contents(base_path('resources/js/Pages/Tickets/Show.vue'));
        $this->assertStringContainsString('Phase-54 COST-UI', $body);
        $this->assertStringContainsString('costSegmentWidth', $body);
    }

    public function test_ticket_policy_has_create_cost_method(): void
    {
        $this->assertTrue(method_exists(\App\Policies\TicketPolicy::class, 'createCost'));
    }

    // -- VENDOR-ONBOARDING -------------------------------------------------

    public function test_vendor_profile_routes_registered(): void
    {
        $this->assertTrue(Route::has('vendor.profile.edit'));
        $this->assertTrue(Route::has('vendor.profile.update'));
    }

    public function test_vendor_observer_registered(): void
    {
        $this->assertTrue(class_exists(\App\Observers\VendorObserver::class));
        $this->assertTrue(class_exists(\App\Mail\VendorCreatedMailable::class));
    }

    public function test_vendor_profile_vue_exists(): void
    {
        $this->assertFileExists(base_path('resources/js/Pages/Vendor/Profile.vue'));
    }

    // -- CI ----------------------------------------------------------------

    public function test_maintenance_runbook_mentions_phase_54(): void
    {
        $body = (string) file_get_contents(base_path('docs/runbooks/maintenance.md'));
        $this->assertStringContainsString('Phase 54', $body);
        $this->assertStringContainsString('MAINTENANCE-DEPTH-2', $body);
    }
}
