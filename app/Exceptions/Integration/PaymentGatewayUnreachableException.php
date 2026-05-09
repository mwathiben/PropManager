<?php

declare(strict_types=1);

namespace App\Exceptions\Integration;

/**
 * HANDLE-1: gateway is not reachable — connection failed, timed out, or
 * DNS resolution failed. The caller should surface a 'try again in a
 * minute' message rather than 'request failed' (which implies the gateway
 * rejected the request). This is the retry-later case.
 */
class PaymentGatewayUnreachableException extends PaymentGatewayException
{
    public const ERROR_CODE = 'PAYMENT_GATEWAY_UNREACHABLE';

    public function __construct(
        string $gateway,
        string $endpoint,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: "{$gateway} is currently unreachable. Please try again in a moment.",
            errorCode: self::ERROR_CODE,
            context: [
                'gateway' => $gateway,
                'endpoint' => $endpoint,
            ],
            statusCode: 503,
            previous: $previous,
        );
    }
}
