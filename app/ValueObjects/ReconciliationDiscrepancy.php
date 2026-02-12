<?php

declare(strict_types=1);

namespace App\ValueObjects;

final readonly class ReconciliationDiscrepancy
{
    public const MISSING_LOCALLY = 'missing_locally';

    public const MISSING_REMOTELY = 'missing_remotely';

    public const AMOUNT_MISMATCH = 'amount_mismatch';

    public function __construct(
        public string $type,
        public string $reference,
        public ?float $localAmount,
        public ?float $remoteAmount,
        public ?string $currency,
        public ?string $remoteStatus,
    ) {}

    public static function missingLocally(
        string $reference,
        float $remoteAmount,
        string $currency,
        string $remoteStatus,
    ): self {
        return new self(
            type: self::MISSING_LOCALLY,
            reference: $reference,
            localAmount: null,
            remoteAmount: $remoteAmount,
            currency: $currency,
            remoteStatus: $remoteStatus,
        );
    }

    public static function missingRemotely(
        string $reference,
        float $localAmount,
        string $currency,
    ): self {
        return new self(
            type: self::MISSING_REMOTELY,
            reference: $reference,
            localAmount: $localAmount,
            remoteAmount: null,
            currency: $currency,
            remoteStatus: null,
        );
    }

    public static function amountMismatch(
        string $reference,
        float $localAmount,
        float $remoteAmount,
        string $currency,
        string $remoteStatus,
    ): self {
        return new self(
            type: self::AMOUNT_MISMATCH,
            reference: $reference,
            localAmount: $localAmount,
            remoteAmount: $remoteAmount,
            currency: $currency,
            remoteStatus: $remoteStatus,
        );
    }
}
