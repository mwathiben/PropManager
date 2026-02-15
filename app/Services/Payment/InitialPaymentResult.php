<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\Currency;
use App\Models\Payment;
use App\Models\TenantPaymentVerification;

readonly class InitialPaymentResult
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_NOT_FOUND = 'not_found';

    public const STATUS_ALREADY_VERIFIED = 'already_verified';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_ERROR = 'error';

    private function __construct(
        public string $status,
        public ?Payment $payment,
        public ?TenantPaymentVerification $verification,
        public float $amount,
        public bool $isVerified,
        public ?string $errorMessage,
    ) {}

    public static function success(
        Payment $payment,
        TenantPaymentVerification $verification,
        float $amount,
        bool $isVerified,
    ): self {
        return new self(
            status: self::STATUS_SUCCESS,
            payment: $payment,
            verification: $verification,
            amount: $amount,
            isVerified: $isVerified,
            errorMessage: null,
        );
    }

    public static function notFound(): self
    {
        return new self(
            status: self::STATUS_NOT_FOUND,
            payment: null,
            verification: null,
            amount: 0,
            isVerified: false,
            errorMessage: 'Payment verification record not found',
        );
    }

    public static function alreadyVerified(): self
    {
        return new self(
            status: self::STATUS_ALREADY_VERIFIED,
            payment: null,
            verification: null,
            amount: 0,
            isVerified: true,
            errorMessage: null,
        );
    }

    public static function duplicate(): self
    {
        return new self(
            status: self::STATUS_DUPLICATE,
            payment: null,
            verification: null,
            amount: 0,
            isVerified: false,
            errorMessage: null,
        );
    }

    public static function error(string $message): self
    {
        return new self(
            status: self::STATUS_ERROR,
            payment: null,
            verification: null,
            amount: 0,
            isVerified: false,
            errorMessage: $message,
        );
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function redirectRoute(): string
    {
        if ($this->status === self::STATUS_ALREADY_VERIFIED) {
            return 'dashboard';
        }

        return 'tenant.payment-required';
    }

    public function flashType(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'success',
            self::STATUS_ALREADY_VERIFIED => 'info',
            self::STATUS_DUPLICATE => 'info',
            self::STATUS_NOT_FOUND, self::STATUS_ERROR => 'error',
        };
    }

    public function flashMessage(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => $this->successMessage(),
            self::STATUS_ALREADY_VERIFIED => 'Payment already verified',
            self::STATUS_DUPLICATE => 'Payment already recorded',
            self::STATUS_NOT_FOUND => 'Payment verification record not found',
            self::STATUS_ERROR => $this->errorMessage ?? 'Failed to record payment. Please contact support.',
        };
    }

    public function successMessage(): string
    {
        $symbol = ($this->payment?->currency ?? Currency::default())->symbol();
        $message = 'Payment of '.$symbol.' '.number_format($this->amount, 2).' successful!';

        if ($this->isVerified) {
            $message .= ' Your account has been verified. Welcome!';
        } else {
            $message .= ' Please wait for verification.';
        }

        return $message;
    }
}
