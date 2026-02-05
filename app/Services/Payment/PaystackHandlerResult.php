<?php

declare(strict_types=1);

namespace App\Services\Payment;

class PaystackHandlerResult
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_ALREADY_PROCESSED = 'already_processed';

    public const STATUS_INITIAL_PAYMENT = 'initial_payment';

    public const STATUS_UNAUTHORIZED = 'unauthorized';

    public const STATUS_BAD_REQUEST = 'bad_request';

    public const STATUS_NOT_CONFIGURED = 'not_configured';

    public const STATUS_VERIFICATION_FAILED = 'verification_failed';

    public const STATUS_AMOUNT_MISMATCH = 'amount_mismatch';

    public const STATUS_IGNORED = 'ignored';

    public const STATUS_ERROR = 'error';

    private function __construct(
        public readonly string $status,
        public readonly ?PaymentProcessResult $processResult = null,
        public readonly array $data = [],
        public readonly array $metadata = [],
        public readonly ?string $errorMessage = null,
        private readonly int $httpStatusCode = 200
    ) {}

    public static function success(PaymentProcessResult $inner): self
    {
        return new self(
            status: self::STATUS_SUCCESS,
            processResult: $inner,
        );
    }

    public static function alreadyProcessed(): self
    {
        return new self(status: self::STATUS_ALREADY_PROCESSED);
    }

    public static function initialPayment(array $data, array $metadata): self
    {
        return new self(
            status: self::STATUS_INITIAL_PAYMENT,
            data: $data,
            metadata: $metadata,
        );
    }

    public static function unauthorized(string $message): self
    {
        return new self(
            status: self::STATUS_UNAUTHORIZED,
            errorMessage: $message,
            httpStatusCode: 401,
        );
    }

    public static function badRequest(string $message): self
    {
        return new self(
            status: self::STATUS_BAD_REQUEST,
            errorMessage: $message,
            httpStatusCode: 400,
        );
    }

    public static function notConfigured(): self
    {
        return new self(
            status: self::STATUS_NOT_CONFIGURED,
            errorMessage: 'Paystack not configured',
            httpStatusCode: 400,
        );
    }

    public static function verificationFailed(): self
    {
        return new self(
            status: self::STATUS_VERIFICATION_FAILED,
            errorMessage: 'Payment verification failed',
        );
    }

    public static function amountMismatch(float $expected, float $actual): self
    {
        return new self(
            status: self::STATUS_AMOUNT_MISMATCH,
            errorMessage: "Amount mismatch: expected {$expected}, got {$actual}",
            httpStatusCode: 400,
        );
    }

    public static function ignored(): self
    {
        return new self(status: self::STATUS_IGNORED);
    }

    public static function error(string $message): self
    {
        return new self(
            status: self::STATUS_ERROR,
            errorMessage: $message,
            httpStatusCode: 500,
        );
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isInitialPayment(): bool
    {
        return $this->status === self::STATUS_INITIAL_PAYMENT;
    }

    public function isAlreadyProcessed(): bool
    {
        return $this->status === self::STATUS_ALREADY_PROCESSED;
    }

    public function isError(): bool
    {
        return in_array($this->status, [
            self::STATUS_ERROR,
            self::STATUS_NOT_CONFIGURED,
            self::STATUS_VERIFICATION_FAILED,
            self::STATUS_AMOUNT_MISMATCH,
            self::STATUS_BAD_REQUEST,
        ]);
    }

    public function isIgnored(): bool
    {
        return $this->status === self::STATUS_IGNORED;
    }

    public function httpStatus(): int
    {
        return $this->httpStatusCode;
    }

    public function toResponse(): array
    {
        if ($this->isSuccess()) {
            return ['status' => 'success'];
        }

        if ($this->isAlreadyProcessed()) {
            return ['status' => 'already_processed'];
        }

        if ($this->isIgnored()) {
            return ['status' => 'ignored'];
        }

        if ($this->errorMessage) {
            return ['error' => $this->errorMessage];
        }

        return ['status' => $this->status];
    }
}
