<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IntaSendPaymentStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $intasendInvoiceId,
        public string $status,
        public ?int $paymentId = null,
        public ?float $amount = null,
        public ?string $mpesaReceipt = null,
        public ?string $failureReason = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('intasend.'.$this->intasendInvoiceId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'intasend_invoice_id' => $this->intasendInvoiceId,
            'status' => $this->status,
            'payment_id' => $this->paymentId,
            'amount' => $this->amount,
            'mpesa_receipt' => $this->mpesaReceipt,
            'failure_reason' => $this->failureReason,
        ];
    }
}
