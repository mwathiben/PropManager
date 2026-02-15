<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\Currency;
use App\Models\Invoice;
use App\Models\Payment;

class ManualPaymentResult
{
    public function __construct(
        public readonly Payment $payment,
        public readonly ?Invoice $invoice,
        public readonly float $overpayment = 0,
    ) {}

    public function hasOverpayment(): bool
    {
        return $this->overpayment > 0;
    }

    public function overpaymentNotification(): ?array
    {
        if (! $this->hasOverpayment() || ! $this->payment->lease_id) {
            return null;
        }

        return [
            'payment_id' => $this->payment->id,
            'lease_id' => $this->payment->lease_id,
            'tenant_id' => $this->invoice?->lease?->tenant_id,
            'overpayment' => $this->overpayment,
        ];
    }

    public function successMessage(): string
    {
        $symbol = ($this->payment->currency ?? Currency::default())->symbol();
        $msg = 'Payment of '.$symbol.' '.number_format((float) $this->payment->amount, 2).' recorded successfully!';

        if ($this->hasOverpayment()) {
            $msg .= ' '.$symbol.' '.number_format($this->overpayment, 2).' credited to wallet.';
        }

        return $msg;
    }
}
