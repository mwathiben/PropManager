<?php

namespace App\Exceptions\Payment;

use App\Exceptions\DomainException;

class PaymentException extends DomainException
{
    public function __construct(
        string $message,
        string $errorCode = 'PAYMENT_ERROR',
        array $context = [],
        int $statusCode = 400
    ) {
        parent::__construct($message, $errorCode, $context, $statusCode);
    }
}
