<?php

declare(strict_types=1);

namespace App\Exceptions\Integration;

/**
 * HANDLE-1: gateway is reachable but rejected our credentials, or required
 * config is missing on our side. The user-facing recovery is 'fix
 * configuration in Settings > Payment Methods'; retry won't help.
 */
class PaymentGatewayConfigException extends PaymentGatewayException
{
    public const ERROR_CODE = 'PAYMENT_GATEWAY_CONFIG';

    public function __construct(
        string $gateway,
        string $reason,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: "{$gateway} is not configured correctly: {$reason}",
            errorCode: self::ERROR_CODE,
            context: [
                'gateway' => $gateway,
                'reason' => $reason,
            ],
            statusCode: 502,
            previous: $previous,
        );
    }
}
