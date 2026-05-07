<?php

namespace App\Exceptions\Payment;

class CurrencyMismatchException extends PaymentException
{
    public function __construct(string $paymentCurrency, string $invoiceCurrency, int $invoiceId)
    {
        parent::__construct(
            "Payment currency ({$paymentCurrency}) does not match invoice currency ({$invoiceCurrency})",
            'CURRENCY_MISMATCH',
            [
                'payment_currency' => $paymentCurrency,
                'invoice_currency' => $invoiceCurrency,
                'invoice_id' => $invoiceId,
            ],
            422
        );
    }
}
