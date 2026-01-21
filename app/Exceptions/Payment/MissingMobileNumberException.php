<?php

namespace App\Exceptions\Payment;

class MissingMobileNumberException extends RefundException
{
    public function __construct(int $tenantId, string $purpose = 'M-Pesa refund')
    {
        parent::__construct(
            message: "Tenant has no mobile number for {$purpose}",
            errorCode: 'REFUND_MISSING_MOBILE',
            context: [
                'tenant_id' => $tenantId,
                'purpose' => $purpose,
            ]
        );
    }
}
