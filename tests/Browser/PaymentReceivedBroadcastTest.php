<?php

namespace Tests\Browser;

use App\Events\PaymentReceived;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PlatformFee;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Browser E2E tests for PaymentReceived broadcast functionality.
 *
 * PREREQUISITES: These tests require the full development environment:
 * - php artisan serve
 * - npm run dev
 * - php artisan reverb:start
 * - php artisan queue:listen
 *
 * These tests are skipped by default. Remove the skip to run manually.
 *
 * Run with: php artisan dusk --filter=PaymentReceivedBroadcastTest
 */
class PaymentReceivedBroadcastTest extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Skip all browser tests by default - requires full dev environment
        $this->markTestSkipped('Requires full dev environment: php artisan serve, npm run dev, php artisan reverb:start. Remove this skip to run manually.');
    }

    public function test_dashboard_loads_and_echo_initializes(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithProperty();

        $this->browse(function (Browser $browser) use ($landlord) {
            $browser->loginAs($landlord)
                ->visit('/dashboard')
                ->waitForText('Recent Payments', 15)
                ->assertSee('Recent Payments');

            // Verify Echo is available
            $echoAvailable = $browser->script('return typeof window.Echo !== "undefined"');
            $this->assertTrue($echoAvailable[0], 'Echo should be initialized on dashboard');
        });
    }

    public function test_recent_payment_displays_with_split_tooltip(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithProperty();

        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
        ]);

        $lease = Lease::factory()->create([
            'unit_id' => $units->first()->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $landlord->id,
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'lease_id' => $lease->id,
            'landlord_id' => $landlord->id,
            'invoice_number' => 'INV-TEST-001',
            'due_date' => now()->addDays(7),
            'billing_period_start' => now()->startOfMonth(),
            'rent_due' => 25000,
            'water_due' => 0,
            'arrears' => 0,
            'total_due' => 25000,
            'status' => 'sent',
        ]);

        // Create payment with platform fee
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $landlord->id,
            'amount' => 25000,
            'payment_method' => 'mobile_money',
            'reference' => 'DUSK-TEST-'.now()->timestamp,
            'payment_date' => now(),
        ]);

        PlatformFee::create([
            'payment_id' => $payment->id,
            'landlord_id' => $landlord->id,
            'gross_amount' => 25000,
            'fee_amount' => 750,
            'net_amount' => 24250,
            'fee_type' => 'transaction_percentage',
            'fee_percentage_applied' => 3.0,
            'status' => 'collected',
        ]);

        // Load the payment with its platformFee relationship
        $payment = $payment->fresh(['platformFee']);

        $this->browse(function (Browser $browser) use ($landlord, $payment, $invoice) {
            $browser->loginAs($landlord)
                ->visit('/dashboard')
                ->waitForText('Recent Payments', 15)
                ->assertSee('Recent Payments');

            // Set up event listener on the window to capture broadcast events
            $browser->script("
                window.__testPaymentEvents = [];
                if (window.Echo) {
                    window.Echo.private('landlord.{$landlord->id}')
                        .listen('PaymentReceived', (e) => {
                            window.__testPaymentEvents.push(e);
                        });
                }
            ");

            // Wait for subscription to be established
            $browser->pause(1000);

            // Dispatch the PaymentReceived event to trigger the broadcast
            event(new PaymentReceived($payment, $invoice));

            // Wait for the payment to appear in the recent payments list
            $browser->waitForText($payment->reference, 15)
                ->assertSee($payment->reference);

            // Assert the gross amount is displayed (25,000)
            $browser->assertSee('25,000');

            // Find the payment row element and hover to trigger tooltip
            // The payment row has a title attribute with split details
            $browser->mouseover('[title*="Net:"]')
                ->pause(500);

            // Verify the tooltip content includes the platform fee (750) and net amount (24250)
            $titleAttr = $browser->attribute('[title*="Net:"]', 'title');
            $this->assertStringContainsString('24,250', $titleAttr, 'Tooltip should show net amount of 24,250');
            $this->assertStringContainsString('750', $titleAttr, 'Tooltip should show platform fee of 750');

            // Verify the event payload was received correctly via window.__testPaymentEvents
            $browser->pause(500);
            $events = $browser->script('return window.__testPaymentEvents');
            $this->assertNotEmpty($events[0], 'Should have received at least one payment event');

            $receivedEvent = $events[0][0];
            $this->assertEquals(25000, $receivedEvent['amount'], 'Event should contain gross amount');
            $this->assertEquals(750, $receivedEvent['platform_fee'], 'Event should contain platform fee');
            $this->assertEquals(24250, $receivedEvent['landlord_amount'], 'Event should contain landlord net amount');
            $this->assertEquals('intasend', $receivedEvent['split_provider'], 'Event should specify intasend as split provider');
        });
    }
}
