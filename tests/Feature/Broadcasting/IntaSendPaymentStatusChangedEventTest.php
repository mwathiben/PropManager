<?php

namespace Tests\Feature\Broadcasting;

use App\Events\IntaSendPaymentStatusChanged;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntaSendPaymentStatusChangedEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcasts_to_intasend_invoice_channel(): void
    {
        $intasendInvoiceId = 'INV_'.uniqid();

        $event = new IntaSendPaymentStatusChanged($intasendInvoiceId, 'success', 1, 5000.00, 'QKL123456789');
        $channels = collect($event->broadcastOn());

        $this->assertTrue(
            $channels->contains(fn ($ch) => $ch instanceof PrivateChannel
                && $ch->name === 'private-intasend.'.$intasendInvoiceId
            ),
            "Event should broadcast to private-intasend.{$intasendInvoiceId}"
        );
    }

    public function test_success_payload_contains_payment_details(): void
    {
        $intasendInvoiceId = 'INV_'.uniqid();
        $paymentId = 42;
        $amount = 5000.00;
        $mpesaReceipt = 'QKL123456789';

        $event = new IntaSendPaymentStatusChanged(
            $intasendInvoiceId,
            'success',
            $paymentId,
            $amount,
            $mpesaReceipt
        );
        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('intasend_invoice_id', $payload);
        $this->assertArrayHasKey('status', $payload);
        $this->assertArrayHasKey('payment_id', $payload);
        $this->assertArrayHasKey('amount', $payload);
        $this->assertArrayHasKey('mpesa_receipt', $payload);
        $this->assertArrayHasKey('failure_reason', $payload);

        $this->assertEquals($intasendInvoiceId, $payload['intasend_invoice_id']);
        $this->assertEquals('success', $payload['status']);
        $this->assertEquals($paymentId, $payload['payment_id']);
        $this->assertEquals($amount, $payload['amount']);
        $this->assertEquals($mpesaReceipt, $payload['mpesa_receipt']);
        $this->assertNull($payload['failure_reason']);
    }

    public function test_failed_payload_contains_failure_reason(): void
    {
        $intasendInvoiceId = 'INV_'.uniqid();
        $failureReason = 'Request cancelled by user';

        $event = new IntaSendPaymentStatusChanged(
            $intasendInvoiceId,
            'failed',
            null,
            5000.00,
            null,
            $failureReason
        );
        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('failure_reason', $payload);
        $this->assertEquals($failureReason, $payload['failure_reason']);
        $this->assertEquals('failed', $payload['status']);
        $this->assertNull($payload['payment_id']);
        $this->assertNull($payload['mpesa_receipt']);
    }

    public function test_processing_payload_has_minimal_data(): void
    {
        $intasendInvoiceId = 'INV_'.uniqid();

        $event = new IntaSendPaymentStatusChanged(
            $intasendInvoiceId,
            'processing',
            null,
            5000.00
        );
        $payload = $event->broadcastWith();

        $this->assertEquals('processing', $payload['status']);
        $this->assertEquals($intasendInvoiceId, $payload['intasend_invoice_id']);
        $this->assertEquals(5000.00, $payload['amount']);
        $this->assertNull($payload['payment_id']);
        $this->assertNull($payload['mpesa_receipt']);
        $this->assertNull($payload['failure_reason']);
    }

    public function test_status_values_are_correct(): void
    {
        $intasendInvoiceId = 'INV_'.uniqid();

        $processingEvent = new IntaSendPaymentStatusChanged($intasendInvoiceId, 'processing', null, 5000.00);
        $successEvent = new IntaSendPaymentStatusChanged($intasendInvoiceId, 'success', 1, 5000.00, 'QKL123');
        $failedEvent = new IntaSendPaymentStatusChanged($intasendInvoiceId, 'failed', null, 5000.00, null, 'User cancelled');

        $this->assertEquals('processing', $processingEvent->broadcastWith()['status']);
        $this->assertEquals('success', $successEvent->broadcastWith()['status']);
        $this->assertEquals('failed', $failedEvent->broadcastWith()['status']);
    }
}
