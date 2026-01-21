<?php

namespace App\Exceptions\Payment;

class UnsupportedPaymentMethodException extends PaymentException
{
    public function __construct(string $method)
    {
        parent::__construct(
            message: "Unsupported payment method: {$method}",
            errorCode: 'PAYMENT_UNSUPPORTED_METHOD',
            context: [
                'payment_method' => $method,
            ]
        );
    }
}
