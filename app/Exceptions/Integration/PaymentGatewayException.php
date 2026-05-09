<?php

declare(strict_types=1);

namespace App\Exceptions\Integration;

use App\Exceptions\DomainException;

/**
 * HANDLE-1: base class for payment-gateway failures that callers need to
 * differentiate. Every subclass corresponds to a distinct user-facing
 * recovery action — retry-later vs. contact-support vs. fix-config.
 */
class PaymentGatewayException extends DomainException
{
    public const GATEWAY_ERROR = 'PAYMENT_GATEWAY_ERROR';

    public function __construct(
        string $message,
        string $errorCode = self::GATEWAY_ERROR,
        array $context = [],
        int $statusCode = 502,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $context, $statusCode, $previous);
    }
}
