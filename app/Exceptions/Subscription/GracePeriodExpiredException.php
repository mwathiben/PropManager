<?php

namespace App\Exceptions\Subscription;

use App\Exceptions\DomainException;

class GracePeriodExpiredException extends DomainException
{
    public function __construct(?int $subscriptionId = null)
    {
        parent::__construct(
            message: 'Cannot resume - grace period has ended',
            errorCode: 'SUBSCRIPTION_GRACE_EXPIRED',
            context: array_filter([
                'subscription_id' => $subscriptionId,
            ]),
            statusCode: 400
        );
    }
}
