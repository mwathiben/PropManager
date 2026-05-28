<?php

declare(strict_types=1);

namespace Tests\Feature\Gateway;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\PaymentConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-41 GATEWAY-CHECKOUT-1/2/3: gateway-agnostic checkout
 * endpoint using routeForUser + receipt currency assertions.
 */
class Phase41CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_route_is_registered(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('payments.checkout.initialize'));
    }

    public function test_checkout_blocks_when_landlord_gateway_unconfigured(): void
    {
        $landlord = User::factory()->create([
            'role' => 'landlord',
            'email_verified_at' => now(),
            'payment_gateway_preference' => 'stripe',
        ]);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id, 'email_verified_at' => now()]);
        $lease = Lease::factory()->create(['landlord_id' => $landlord->id, 'tenant_id' => $tenant->id]);
        $invoice = Invoice::factory()->create([
            'landlord_id' => $landlord->id,
            'lease_id' => $lease->id,
            'currency' => 'USD',
            // Must be payable so the request passes the status gate and reaches the
            // unconfigured-gateway check (Phase-99 added a payable-status guard).
            'status' => \App\Enums\InvoiceStatus::Sent,
        ]);

        // No Stripe creds on PaymentConfiguration → gateway is not configured.
        $response = $this->actingAs($tenant)->postJson(
            route('payments.checkout.initialize', ['invoice' => $invoice->id]),
            ['amount' => 100, 'gateway' => 'stripe'],
        );

        $this->assertContains($response->status(), [400, 422]);
    }

    public function test_no_hardcoded_kes_in_receipt_templates(): void
    {
        // CHECKOUT-3: receipts must use $payment->currency or $currency_symbol,
        // not a hardcoded 'KES' literal. Grep the receipts dir for offenders.
        $files = glob(resource_path('views/receipts/*.blade.php')) ?: [];
        $offenders = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            // Match KES surrounded by non-alphanumeric on both sides (so KESu doesn't false-flag).
            if (preg_match("/['\"]KES['\"]/", $content)) {
                $offenders[] = basename($file);
            }
        }
        $this->assertEmpty($offenders, 'Hardcoded KES literal in: '.implode(', ', $offenders));
    }
}
