<?php

declare(strict_types=1);

namespace App\ValueObjects\Payment;

final readonly class PaymentResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public bool $success,
        public ?string $reference = null,
        public ?string $authorizationUrl = null,
        public ?string $accessCode = null,
        public ?string $transactionId = null,
        public ?string $status = null,
        public ?Money $amount = null,
        public ?string $error = null,
        public ?string $errorCode = null,
        public array $rawResponse = [],
    ) {}

    /**
     * Create a successful result for payment initialization.
     *
     * @param  array<string, mixed>  $rawResponse
     */
    public static function initialized(
        string $reference,
        string $authorizationUrl,
        ?string $accessCode = null,
        array $rawResponse = [],
    ): self {
        return new self(
            success: true,
            reference: $reference,
            authorizationUrl: $authorizationUrl,
            accessCode: $accessCode,
            status: 'initialized',
            rawResponse: $rawResponse,
        );
    }

    /**
     * Create a successful result for payment verification.
     *
     * @param  array<string, mixed>  $rawResponse
     */
    public static function verified(
        string $reference,
        string $status,
        Money $amount,
        ?string $transactionId = null,
        array $rawResponse = [],
    ): self {
        return new self(
            success: $status === 'success',
            reference: $reference,
            transactionId: $transactionId,
            status: $status,
            amount: $amount,
            rawResponse: $rawResponse,
        );
    }

    /**
     * Create a successful refund result.
     *
     * @param  array<string, mixed>  $rawResponse
     */
    public static function refunded(
        string $reference,
        Money $amount,
        ?string $transactionId = null,
        array $rawResponse = [],
    ): self {
        return new self(
            success: true,
            reference: $reference,
            transactionId: $transactionId,
            status: 'refunded',
            amount: $amount,
            rawResponse: $rawResponse,
        );
    }

    /**
     * Create a failed result.
     *
     * @param  array<string, mixed>  $rawResponse
     */
    public static function failed(
        string $error,
        ?string $errorCode = null,
        ?string $reference = null,
        array $rawResponse = [],
    ): self {
        return new self(
            success: false,
            reference: $reference,
            status: 'failed',
            error: $error,
            errorCode: $errorCode,
            rawResponse: $rawResponse,
        );
    }

    /**
     * Create a pending result (for async payments like M-Pesa STK).
     *
     * @param  array<string, mixed>  $rawResponse
     */
    public static function pending(
        string $reference,
        ?string $transactionId = null,
        array $rawResponse = [],
    ): self {
        return new self(
            success: true,
            reference: $reference,
            transactionId: $transactionId,
            status: 'pending',
            rawResponse: $rawResponse,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isVerified(): bool
    {
        return $this->success && $this->status === 'success';
    }

    public function hasAuthorizationUrl(): bool
    {
        return $this->authorizationUrl !== null;
    }
}
