<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MpesaPaymentStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $checkoutRequestId,
        public string $status,
        public ?int $paymentId = null,
        public ?float $amount = null,
        public ?string $mpesaReceipt = null,
        public ?string $message = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('mpesa.'.$this->checkoutRequestId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'checkout_request_id' => $this->checkoutRequestId,
            'status' => $this->status,
            'payment_id' => $this->paymentId,
            'amount' => $this->amount,
            'mpesa_receipt' => $this->mpesaReceipt,
            'message' => $this->message,
        ];
    }
}
