<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-41 GATEWAY-WEBHOOK-DEEP-2: fired when a gateway-initiated
 * refund (Stripe charge.refunded) flips a local Payment to voided.
 * Listeners can notify tenant + landlord that the refund posted.
 */
class PaymentRefundedExternal
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly string $gateway,
    ) {}
}
