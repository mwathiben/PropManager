<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Models\CheckoutSession;
use App\Models\CheckoutSessionItem;
use App\Models\User;
use App\Services\Checkout\CartCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-42 CART-1/2/3: checkout_sessions + checkout_session_items
 * tables + CartCheckoutService currency grouping +
 * CartCheckoutController routes.
 */
class Phase42CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_sessions_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('checkout_sessions'));
        $cols = Schema::getColumnListing('checkout_sessions');
        foreach (['landlord_id', 'tenant_id', 'status', 'total_amount_cents', 'currency_breakdown', 'expires_at', 'succeeded_at'] as $c) {
            $this->assertContains($c, $cols);
        }
    }

    public function test_checkout_session_items_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('checkout_session_items'));
        $cols = Schema::getColumnListing('checkout_session_items');
        foreach (['checkout_session_id', 'line_type', 'line_id', 'amount_cents', 'currency', 'description', 'stripe_payment_intent_id'] as $c) {
            $this->assertContains($c, $cols);
        }
    }

    public function test_checkout_session_isopen_for_open_and_submitted(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);

        $session = CheckoutSession::create([
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'status' => CheckoutSession::STATUS_OPEN,
        ]);
        $this->assertTrue($session->isOpen());

        $session->update(['status' => CheckoutSession::STATUS_SUBMITTED]);
        $this->assertTrue($session->fresh()->isOpen());

        $session->update(['status' => CheckoutSession::STATUS_SUCCEEDED]);
        $this->assertFalse($session->fresh()->isOpen());
    }

    public function test_cart_service_groups_items_by_currency(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);

        $session = CheckoutSession::create([
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'status' => CheckoutSession::STATUS_OPEN,
        ]);
        // 2 KES lines + 1 USD line
        CheckoutSessionItem::create([
            'checkout_session_id' => $session->id,
            'line_type' => CheckoutSessionItem::TYPE_INVOICE,
            'line_id' => 1, 'amount_cents' => 100000, 'currency' => 'KES', 'description' => 'Rent',
        ]);
        CheckoutSessionItem::create([
            'checkout_session_id' => $session->id,
            'line_type' => CheckoutSessionItem::TYPE_INVOICE,
            'line_id' => 2, 'amount_cents' => 50000, 'currency' => 'KES', 'description' => 'Water',
        ]);
        CheckoutSessionItem::create([
            'checkout_session_id' => $session->id,
            'line_type' => CheckoutSessionItem::TYPE_ADD_ON,
            'line_id' => 3, 'amount_cents' => 5000, 'currency' => 'USD', 'description' => 'Add-on',
        ]);

        // Force the user to fall back to currency-routing.
        $landlord->payment_gateway_preference = 'auto';
        $landlord->save();

        $service = app(CartCheckoutService::class);
        $groups = $service->initialize($session->fresh());

        $this->assertArrayHasKey('KES', $groups);
        $this->assertArrayHasKey('USD', $groups);
        $this->assertSame(150000, $groups['KES']['amount_cents']);
        $this->assertSame(5000, $groups['USD']['amount_cents']);
        $this->assertSame(2, $groups['KES']['line_count']);
        $this->assertSame(1, $groups['USD']['line_count']);

        $session->refresh();
        $this->assertSame(CheckoutSession::STATUS_SUBMITTED, $session->status);
        $this->assertSame(155000, $session->total_amount_cents);
    }

    public function test_cart_service_empty_session_returns_empty_groups(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $session = CheckoutSession::create([
            'landlord_id' => $landlord->id,
            'status' => CheckoutSession::STATUS_OPEN,
        ]);

        $service = app(CartCheckoutService::class);
        $groups = $service->initialize($session);

        $this->assertSame([], $groups);
    }

    public function test_initialize_endpoint_rejects_non_owner(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $other = User::factory()->create(['role' => 'tenant']);
        $session = CheckoutSession::create([
            'landlord_id' => $landlord->id,
            'tenant_id' => User::factory()->create(['role' => 'tenant'])->id,
            'status' => CheckoutSession::STATUS_OPEN,
        ]);

        $response = $this->actingAs($other)
            ->postJson("/checkout/sessions/{$session->id}/initialize");

        $response->assertStatus(403);
    }

    public function test_initialize_endpoint_rejects_closed_session(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);
        $session = CheckoutSession::create([
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'status' => CheckoutSession::STATUS_SUCCEEDED,
        ]);

        $response = $this->actingAs($tenant)
            ->postJson("/checkout/sessions/{$session->id}/initialize");

        $response->assertStatus(422);
    }

    public function test_initialize_endpoint_returns_currency_groups_for_tenant(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord', 'payment_gateway_preference' => 'auto']);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);
        $session = CheckoutSession::create([
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
            'status' => CheckoutSession::STATUS_OPEN,
        ]);
        CheckoutSessionItem::create([
            'checkout_session_id' => $session->id,
            'line_type' => CheckoutSessionItem::TYPE_INVOICE,
            'line_id' => 1,
            'amount_cents' => 100000,
            'currency' => 'KES',
            'description' => 'Rent',
        ]);

        $response = $this->actingAs($tenant)
            ->postJson("/checkout/sessions/{$session->id}/initialize");

        $response->assertStatus(200);
        $response->assertJsonStructure(['session_id', 'status', 'currency_groups']);
        $this->assertSame($session->id, $response->json('session_id'));
        $this->assertArrayHasKey('KES', $response->json('currency_groups'));
    }

    public function test_checkout_session_items_polymorphic_line_type_accepts_valid_types(): void
    {
        $this->assertSame(
            ['invoice', 'add_on', 'deposit'],
            CheckoutSessionItem::TYPES,
        );
    }
}
