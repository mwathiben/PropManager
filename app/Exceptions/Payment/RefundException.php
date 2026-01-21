<?php

namespace App\Exceptions\Payment;

class RefundException extends PaymentException
{
    public function __construct(
        string $message,
        string $errorCode = 'REFUND_ERROR',
        array $context = [],
        int $statusCode = 400
    ) {
        parent::__construct($message, $errorCode, $context, $statusCode);
    }
}
