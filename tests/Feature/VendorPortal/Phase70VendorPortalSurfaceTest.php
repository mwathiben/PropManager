<?php

declare(strict_types=1);

namespace Tests\Feature\VendorPortal;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-70 VENDOR-PORTAL CI (CI-1): cross-category surface map. Guards the
 * portal's middleware, controllers, services, columns, routes, Vue, lang,
 * and command across all five feature sub-phases.
 */
class Phase70VendorPortalSurfaceTest extends TestCase
{
    private function vue(string $relative): void
    {
        $this->assertFileExists(base_path('resources/js/'.$relative));
    }

    public function test_classes_exist(): void
    {
        foreach ([
            \App\Http\Middleware\EnsureVendorPortal::class,
            \App\Http\Controllers\VendorPortalController::class,
            \App\Http\Controllers\VendorPortalTicketController::class,
            \App\Http\Controllers\VendorPortalStatementController::class,
            \App\Http\Controllers\VendorPortalSlaController::class,
            \App\Services\Vendors\VendorPortalLinkService::class,
            \App\Services\Vendors\VendorStatementService::class,
            \App\Services\Vendors\VendorSlaService::class,
            \App\Services\Maintenance\VendorAssignmentResponseService::class,
            \App\Services\Maintenance\VendorTimeLogService::class,
            \App\Services\Maintenance\VendorJobResolutionService::class,
            \App\Models\TicketTimeLog::class,
            \App\Mail\VendorPortalLinkMailable::class,
            \App\Mail\VendorDeclinedMailable::class,
            \App\Events\VendorDeclinedAssignment::class,
            \App\Listeners\NotifyLandlordOnVendorDecline::class,
        ] as $class) {
            $this->assertTrue(class_exists($class) || interface_exists($class), "{$class} must exist");
        }
    }

    public function test_schema_present(): void
    {
        $this->assertTrue(Schema::hasColumn('tickets', 'vendor_status'));
        $this->assertTrue(Schema::hasColumn('tickets', 'vendor_responded_at'));
        $this->assertTrue(Schema::hasTable('ticket_time_logs'));
        $this->assertTrue(Schema::hasColumn('ticket_time_logs', 'minutes'));
    }

    public function test_routes_registered(): void
    {
        foreach ([
            'vendor.portal.enter', 'vendor.portal.dashboard', 'vendor.portal.logout',
            'vendor.portal.inbox', 'vendor.portal.tickets.accept', 'vendor.portal.tickets.decline',
            'vendor.portal.tickets.show', 'vendor.portal.tickets.time', 'vendor.portal.tickets.resolve',
            'vendor.portal.statement', 'vendor.portal.statement.export', 'vendor.portal.sla',
            'finances.vendors.portal-link',
        ] as $name) {
            $this->assertTrue(Route::has($name), "route {$name} must be registered");
        }
    }

    public function test_vue_pages_exist(): void
    {
        $this->vue('Layouts/VendorPortalLayout.vue');
        foreach (['Dashboard', 'Inbox', 'Job', 'Statement', 'Sla'] as $page) {
            $this->vue("Pages/VendorPortal/{$page}.vue");
        }
    }

    public function test_command_and_lang_present(): void
    {
        $this->assertArrayHasKey('vendor:portal-link', Artisan::all());
        $this->assertIsArray(__('vendor_portal.nav'));
        $this->assertIsArray(__('vendor_portal.inbox'));
        $this->assertIsArray(__('vendor_portal.job'));
        $this->assertIsArray(__('vendor_portal.statement'));
        // The SLA keyspace moved to its own file (vendor_portal_sla.php)
        // in i18n batch 32 — Pages/VendorPortal/Sla.vue consumes
        // vendor_portal_sla.* keys.
        $this->assertIsArray(__('vendor_portal_sla'));
    }

    public function test_portal_routes_are_session_guarded(): void
    {
        // Without a portal session every portal page is forbidden.
        foreach (['/v/portal', '/v/portal/jobs', '/v/portal/statement', '/v/portal/sla'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }
}
