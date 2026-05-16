<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Enums\Currency;
use App\Models\User;
use App\Services\Gateways\PaystackGateway;
use App\Services\Gateways\StripeGateway;
use App\Services\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-40 GATEWAY-PREF-1/2: users.payment_gateway_preference
 * enum + AdminGatewaysController super_admin gateway switcher.
 */
class Phase40GatewayPrefTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_payment_gateway_preference_column(): void
    {
        $cols = \Schema::getColumnListing('users');
        $this->assertContains('payment_gateway_preference', $cols);
    }

    public function test_default_preference_is_auto(): void
    {
        $user = User::factory()->create();
        $this->assertSame('auto', $user->fresh()->payment_gateway_preference);
    }

    public function test_route_for_user_auto_falls_back_to_currency_routing(): void
    {
        $user = User::factory()->create(['payment_gateway_preference' => 'auto']);
        $manager = app(PaymentGatewayManager::class);

        $this->assertInstanceOf(PaystackGateway::class, $manager->routeForUser($user, Currency::KES));
        $this->assertInstanceOf(StripeGateway::class, $manager->routeForUser($user, Currency::USD));
    }

    public function test_route_for_user_forced_paystack_always_returns_paystack(): void
    {
        $user = User::factory()->create(['payment_gateway_preference' => 'paystack']);
        $manager = app(PaymentGatewayManager::class);

        $this->assertInstanceOf(PaystackGateway::class, $manager->routeForUser($user, Currency::USD));
    }

    public function test_route_for_user_forced_stripe_always_returns_stripe(): void
    {
        $user = User::factory()->create(['payment_gateway_preference' => 'stripe']);
        $manager = app(PaymentGatewayManager::class);

        $this->assertInstanceOf(StripeGateway::class, $manager->routeForUser($user, Currency::KES));
    }

    public function test_admin_gateways_index_blocks_landlord(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord', 'email_verified_at' => now()]);
        $this->actingAs($landlord)->get(route('admin.gateways.index'))->assertForbidden();
    }

    public function test_admin_gateways_index_renders_for_super_admin(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
        $response = $this->actingAs($admin)->get(route('admin.gateways.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Gateways/Index')->has('rows'));
    }

    public function test_admin_can_update_landlord_gateway_preference(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
        $landlord = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($admin)->post(
            route('admin.gateways.update', ['user' => $landlord->id]),
            ['preference' => 'stripe'],
        );

        $response->assertRedirect();
        $this->assertSame('stripe', $landlord->fresh()->payment_gateway_preference);
    }

    public function test_admin_update_validates_preference_enum(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
        $landlord = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($admin)->post(
            route('admin.gateways.update', ['user' => $landlord->id]),
            ['preference' => 'venmo'],
        );

        $response->assertSessionHasErrors(['preference']);
    }
}
