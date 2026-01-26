<?php

declare(strict_types=1);

namespace App\ValueObjects\Payment;

final readonly class PaymentRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public Money $amount,
        public string $reference,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $description = null,
        public ?string $callbackUrl = null,
        public array $metadata = [],
    ) {}

    /**
     * Create a PaymentRequest from common parameters.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function create(
        float $amount,
        string $reference,
        ?string $email = null,
        ?string $phone = null,
        string $currency = 'KES',
        ?string $description = null,
        ?string $callbackUrl = null,
        array $metadata = [],
    ): self {
        return new self(
            amount: Money::fromFloat($amount, $currency),
            reference: $reference,
            email: $email,
            phone: $phone,
            description: $description,
            callbackUrl: $callbackUrl,
            metadata: $metadata,
        );
    }

    /**
     * Create a copy with additional metadata.
     *
     * @param  array<string, mixed>  $additional
     */
    public function withMetadata(array $additional): self
    {
        return new self(
            amount: $this->amount,
            reference: $this->reference,
            email: $this->email,
            phone: $this->phone,
            description: $this->description,
            callbackUrl: $this->callbackUrl,
            metadata: array_merge($this->metadata, $additional),
        );
    }

    /**
     * Create a copy with a callback URL.
     */
    public function withCallbackUrl(string $url): self
    {
        return new self(
            amount: $this->amount,
            reference: $this->reference,
            email: $this->email,
            phone: $this->phone,
            description: $this->description,
            callbackUrl: $url,
            metadata: $this->metadata,
        );
    }
}
