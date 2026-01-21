<?php

namespace App\Exceptions\Integration;

use App\Exceptions\DomainException;

class PaystackException extends DomainException
{
    public const BANK_VERIFICATION_FAILED = 'PAYSTACK_BANK_VERIFICATION_FAILED';

    public const API_ERROR = 'PAYSTACK_API_ERROR';

    public function __construct(
        string $message,
        string $errorCode = self::API_ERROR,
        array $context = [],
        int $statusCode = 502
    ) {
        parent::__construct($message, $errorCode, $context, $statusCode);
    }

    public static function bankVerificationFailed(?string $accountNumber = null): self
    {
        return new self(
            message: 'Could not verify bank account. Please check account details.',
            errorCode: self::BANK_VERIFICATION_FAILED,
            context: array_filter([
                'account_number' => $accountNumber,
            ])
        );
    }
}
