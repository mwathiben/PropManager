<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentAmountMismatch
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $provider,
        public string $transactionReference,
        public float $expectedAmount,
        public float $receivedAmount,
        public ?int $landlordId = null,
        public ?int $invoiceId = null
    ) {}
}
