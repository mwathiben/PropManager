<?php

namespace Tests\Feature\Broadcasting;

use App\Events\PaymentReceived;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PlatformFee;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;
use Tests\Traits\MocksExternalServices;

class PaymentReceivedEventTest extends TestCase
{
    use CreatesTestData, MocksExternalServices, RefreshDatabase;

    private Lease $lease;

    private Payment $payment;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $setup = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant, 'lease' => $this->lease] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first()
        );
        ['payment' => $this->payment, 'invoice' => $this->invoice] = $this->createPaymentWithInvoice($this->lease);
    }

    public function test_payment_received_broadcasts_to_landlord_channel(): void
    {
        $event = new PaymentReceived($this->payment, $this->invoice);
        $channels = collect($event->broadcastOn());

        $this->assertTrue(
            $channels->contains(fn ($ch) => $ch instanceof PrivateChannel
                && $ch->name === 'private-landlord.'.$this->lease->landlord_id
            ),
            "Event should broadcast to private-landlord.{$this->lease->landlord_id}"
        );
    }

    public function test_payment_received_broadcasts_to_tenant_channel(): void
    {
        $event = new PaymentReceived($this->payment, $this->invoice);
        $channels = collect($event->broadcastOn());

        $this->assertTrue(
            $channels->contains(fn ($ch) => $ch instanceof PrivateChannel
                && $ch->name === 'private-tenant.'.$this->lease->tenant_id
            ),
            "Event should broadcast to private-tenant.{$this->lease->tenant_id}"
        );
    }

    public function test_broadcast_payload_contains_required_fields(): void
    {
        $this->mockDashboardService();

        $event = new PaymentReceived($this->payment, $this->invoice);
        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('payment_id', $payload);
        $this->assertArrayHasKey('amount', $payload);
        $this->assertArrayHasKey('reference', $payload);
        $this->assertArrayHasKey('payment_method', $payload);
        $this->assertArrayHasKey('invoice_id', $payload);
        $this->assertArrayHasKey('invoice_status', $payload);
        $this->assertArrayHasKey('remaining_balance', $payload);
        $this->assertArrayHasKey('tenant_name', $payload);
        $this->assertArrayHasKey('unit_name', $payload);
        $this->assertArrayHasKey('updated_metrics', $payload);
    }

    public function test_broadcast_payload_amount_is_float(): void
    {
        $this->mockDashboardService();

        $event = new PaymentReceived($this->payment, $this->invoice);
        $payload = $event->broadcastWith();

        $this->assertIsFloat($payload['amount']);
        $this->assertIsFloat($payload['remaining_balance']);
    }

    public function test_updated_metrics_included_in_payload(): void
    {
        $expectedMetrics = [
            'monthly_revenue' => 200000,
            'collection_rate' => 90.0,
        ];

        $this->mockDashboardService($expectedMetrics);

        $event = new PaymentReceived($this->payment, $this->invoice);
        $payload = $event->broadcastWith();

        $this->assertEquals(200000, $payload['updated_metrics']['monthly_revenue']);
        $this->assertEquals(90.0, $payload['updated_metrics']['collection_rate']);
    }

    public function test_event_implements_should_broadcast(): void
    {
        $event = new PaymentReceived($this->payment, $this->invoice);

        $this->assertInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class, $event);
    }

    public function test_payload_contains_split_details_when_platform_fee_exists(): void
    {
        $this->mockDashboardService();

        // Create payment with mobile_money method (IntaSend)
        $this->payment->update(['payment_method' => 'mobile_money']);

        // Create platform fee for this payment directly (without factory to avoid Payment::factory() call)
        PlatformFee::create([
            'payment_id' => $this->payment->id,
            'landlord_id' => $this->lease->landlord_id,
            'gross_amount' => 1000.00,
            'fee_amount' => 100.00,
            'net_amount' => 900.00,
            'fee_type' => 'transaction_percentage',
            'fee_percentage_applied' => 10.00,
            'status' => 'collected',
        ]);

        $event = new PaymentReceived($this->payment->fresh(), $this->invoice);
        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('platform_fee', $payload);
        $this->assertArrayHasKey('landlord_amount', $payload);
        $this->assertArrayHasKey('split_provider', $payload);
        $this->assertEquals(100.0, $payload['platform_fee']);
        $this->assertEquals(900.0, $payload['landlord_amount']);
        $this->assertEquals('intasend', $payload['split_provider']);
    }

    public function test_payload_has_null_platform_fee_for_cash_payments(): void
    {
        $this->mockDashboardService();

        // Cash payment - no platform fee expected
        $this->payment->update(['payment_method' => 'cash', 'amount' => 1000.00]);

        $event = new PaymentReceived($this->payment->fresh(), $this->invoice);
        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('platform_fee', $payload);
        $this->assertArrayHasKey('landlord_amount', $payload);
        $this->assertArrayHasKey('split_provider', $payload);
        $this->assertNull($payload['platform_fee']);
        $this->assertEquals(1000.0, $payload['landlord_amount']);
        $this->assertNull($payload['split_provider']);
    }

    public function test_split_provider_is_intasend_for_mobile_money(): void
    {
        $this->mockDashboardService();

        $this->payment->update(['payment_method' => 'mobile_money']);

        $event = new PaymentReceived($this->payment->fresh(), $this->invoice);
        $payload = $event->broadcastWith();

        $this->assertEquals('intasend', $payload['split_provider']);
    }

    public function test_split_provider_is_paystack_for_paystack_payments(): void
    {
        $this->mockDashboardService();

        $this->payment->update(['payment_method' => 'paystack']);

        $event = new PaymentReceived($this->payment->fresh(), $this->invoice);
        $payload = $event->broadcastWith();

        $this->assertEquals('paystack', $payload['split_provider']);
    }

    public function test_split_provider_is_null_for_bank_transfer(): void
    {
        $this->mockDashboardService();

        $this->payment->update(['payment_method' => 'bank_transfer']);

        $event = new PaymentReceived($this->payment->fresh(), $this->invoice);
        $payload = $event->broadcastWith();

        $this->assertNull($payload['split_provider']);
    }

    public function test_landlord_amount_equals_payment_amount_when_no_platform_fee(): void
    {
        $this->mockDashboardService();

        $this->payment->update(['payment_method' => 'cash', 'amount' => 5000.00]);

        $event = new PaymentReceived($this->payment->fresh(), $this->invoice);
        $payload = $event->broadcastWith();

        $this->assertEquals(5000.0, $payload['landlord_amount']);
        $this->assertNull($payload['platform_fee']);
    }
}
