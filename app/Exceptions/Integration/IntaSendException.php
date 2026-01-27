<?php

namespace App\Exceptions\Integration;

use App\Exceptions\DomainException;

class IntaSendException extends DomainException
{
    public const API_ERROR = 'INTASEND_API_ERROR';

    public const STK_PUSH_FAILED = 'INTASEND_STK_PUSH_FAILED';

    public const VERIFICATION_FAILED = 'INTASEND_VERIFICATION_FAILED';

    public const NOT_CONFIGURED = 'INTASEND_NOT_CONFIGURED';

    public const INVALID_PHONE = 'INTASEND_INVALID_PHONE';

    public function __construct(
        string $message,
        string $errorCode = self::API_ERROR,
        array $context = [],
        int $statusCode = 502
    ) {
        parent::__construct($message, $errorCode, $context, $statusCode);
    }

    public static function notConfigured(): self
    {
        return new self(
            message: 'IntaSend is not configured for this landlord.',
            errorCode: self::NOT_CONFIGURED,
            statusCode: 503
        );
    }

    public static function stkPushFailed(?string $reason = null): self
    {
        return new self(
            message: $reason ?? 'M-Pesa STK Push request failed.',
            errorCode: self::STK_PUSH_FAILED,
            context: array_filter(['reason' => $reason])
        );
    }

    public static function verificationFailed(string $invoiceId): self
    {
        return new self(
            message: 'Failed to verify transaction status.',
            errorCode: self::VERIFICATION_FAILED,
            context: ['invoice_id' => $invoiceId]
        );
    }

    public static function invalidPhoneNumber(string $phone): self
    {
        $maskedPhone = self::maskAccountNumber($phone);

        return new self(
            message: 'Invalid Kenyan phone number format.',
            errorCode: self::INVALID_PHONE,
            context: array_filter(['phone' => $maskedPhone]),
            statusCode: 422
        );
    }
}
