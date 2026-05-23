<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\User;
use App\Models\WaterConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-94 WATER-CLIENTS-FOUNDATION surface guard: schema, model/policy/role
 * plumbing, routes, lang parity.
 */
class Phase94WaterClientsFoundationSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_exists(): void
    {
        $this->assertTrue(Schema::hasTable('water_connections'));
        $this->assertTrue(Schema::hasColumns('water_connections', [
            'landlord_id', 'user_id', 'unit_id', 'meter_id', 'identifier', 'client_name',
            'billing_mode', 'client_rate', 'status', 'connected_at', 'notes', 'deleted_at',
        ]));
        $this->assertTrue(Schema::hasColumns('payment_configurations', ['supplies_water_clients', 'water_client_rate']));
    }

    public function test_policy_registered(): void
    {
        $this->assertNotNull(Gate::getPolicyFor(WaterConnection::class));
    }

    public function test_billing_modes_const(): void
    {
        $this->assertSame(['metered', 'flat_rate'], WaterConnection::BILLING_MODES);
    }

    public function test_water_client_role_helper_exists(): void
    {
        $this->assertTrue(method_exists(User::class, 'isWaterClient'));
    }

    public function test_tenant_scope_handles_water_client(): void
    {
        // The water_client global-scope branch (landlord_id keying) exists.
        $src = file_get_contents(app_path('Traits/TenantScope.php'));
        $this->assertStringContainsString("'water_client'", $src);
    }

    public function test_self_registration_does_not_expose_water_client(): void
    {
        // Water clients are landlord-provisioned (Phase 95), never self-registered —
        // self-registering one would fail the onboarding_sessions role ENUM.
        $src = file_get_contents(app_path('Http/Controllers/Auth/RegisteredUserController.php'));
        $this->assertStringContainsString("'in:landlord,caretaker,tenant'", $src);
        $this->assertStringNotContainsString('in:landlord,caretaker,tenant,water_client', $src);
    }

    public function test_routes_registered(): void
    {
        $this->assertTrue(Route::has('water.clients.setup'));
        $this->assertTrue(Route::has('water.connections.store'));
        $this->assertTrue(Route::has('water.connections.update'));
        $this->assertTrue(Route::has('water.connections.destroy'));
    }

    public function test_lang_parity(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            $water = require base_path("lang/{$locale}/water.php");
            $this->assertArrayHasKey('clients', $water['tabs'] ?? [], "{$locale} missing water.tabs.clients");
            $clients = $water['clients'] ?? [];
            foreach (['intro_title', 'declare_q', 'default_rate', 'manage_title', 'add_line', 'form_title_new', 'wizard'] as $key) {
                $this->assertArrayHasKey($key, $clients, "{$locale} missing water.clients.{$key}");
            }
        }
    }
}
