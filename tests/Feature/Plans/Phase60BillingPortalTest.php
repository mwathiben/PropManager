<?php

declare(strict_types=1);

namespace Tests\Feature\Plans;

use App\Exceptions\BillingPortalUnavailable;
use App\Models\StripeCustomer;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Phase-60 BILLING-PORTAL-1/2/3: StripeService session creation +
 * /subscription/billing/portal redirect route.
 */
class Phase60BillingPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_throws_when_landlord_has_no_stripe_customer(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);

        $this->expectException(BillingPortalUnavailable::class);
        app(StripeService::class)->createBillingPortalSession($user, 'https://example.com/return');
    }

    public function test_service_throws_translation_key_for_missing_customer(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);

        try {
            app(StripeService::class)->createBillingPortalSession($user, 'https://example.com');
            $this->fail('Expected BillingPortalUnavailable');
        } catch (BillingPortalUnavailable $e) {
            $this->assertSame('billing.portal_not_provisioned', $e->translationKey());
        }
    }

    public function test_service_throws_when_gateway_not_configured(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        StripeCustomer::create([
            'user_id' => $user->id,
            'stripe_customer_id' => 'cus_test',
        ]);

        // No STRIPE_SECRET_KEY in test env → isConfigured returns false.
        try {
            app(StripeService::class)->createBillingPortalSession($user, 'https://example.com');
            $this->fail('Expected BillingPortalUnavailable');
        } catch (BillingPortalUnavailable $e) {
            $this->assertSame('billing.portal_gateway_not_configured', $e->translationKey());
        }
    }

    public function test_portal_route_flashes_error_when_unavailable(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($user)
            ->from('/subscription')
            ->post('/subscription/billing/portal');

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_portal_route_redirects_to_stripe_url_on_success(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        StripeCustomer::create([
            'user_id' => $user->id,
            'stripe_customer_id' => 'cus_test',
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('createBillingPortalSession')
            ->andReturn('https://billing.stripe.com/session/abc123');
        app()->instance(StripeService::class, $stripe);

        $response = $this->actingAs($user)
            ->post('/subscription/billing/portal');

        $response->assertRedirect('https://billing.stripe.com/session/abc123');
    }
}
