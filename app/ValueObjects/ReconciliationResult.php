<?php

declare(strict_types=1);

namespace App\ValueObjects;

final readonly class ReconciliationResult
{
    public const TOLERANCE = 0.01;

    public const MAX_PAGES = 50;

    /**
     * @param  array<int, ReconciliationDiscrepancy>  $discrepancies
     */
    public function __construct(
        public array $discrepancies,
        public int $localCount,
        public int $remoteCount,
        public int $matchedCount,
        public string $reconciledAt,
    ) {}

    public function hasDiscrepancies(): bool
    {
        return count($this->discrepancies) > 0;
    }

    public function discrepancyCount(): int
    {
        return count($this->discrepancies);
    }

    /**
     * @return array<int, ReconciliationDiscrepancy>
     */
    public function ofType(string $type): array
    {
        return array_values(
            array_filter($this->discrepancies, fn (ReconciliationDiscrepancy $d) => $d->type === $type)
        );
    }

    /** @return array<int, ReconciliationDiscrepancy> */
    public function missingLocally(): array
    {
        return $this->ofType(ReconciliationDiscrepancy::MISSING_LOCALLY);
    }

    /** @return array<int, ReconciliationDiscrepancy> */
    public function missingRemotely(): array
    {
        return $this->ofType(ReconciliationDiscrepancy::MISSING_REMOTELY);
    }

    /** @return array<int, ReconciliationDiscrepancy> */
    public function amountMismatches(): array
    {
        return $this->ofType(ReconciliationDiscrepancy::AMOUNT_MISMATCH);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(fn (ReconciliationDiscrepancy $d) => [
            'type' => $d->type,
            'reference' => $d->reference,
            'local_amount' => $d->localAmount,
            'remote_amount' => $d->remoteAmount,
            'currency' => $d->currency,
            'remote_status' => $d->remoteStatus,
        ], $this->discrepancies);
    }
}
