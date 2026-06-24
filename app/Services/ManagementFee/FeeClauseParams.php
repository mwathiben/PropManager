<?php

declare(strict_types=1);

namespace App\Services\ManagementFee;

use App\Enums\ManagementFeeBase;
use App\Enums\ManagementFeeFlatCadence;
use App\Enums\ManagementFeeType;
use InvalidArgumentException;

/**
 * Slice-2: the typed, validated view of a fee clause's params.
 *
 * The fee agreement-clause stores its governed values as JSON; this VO is the
 * fail-closed boundary that turns that loose blob into the same strongly-typed
 * fee model the rest of the app uses ({@see ManagementFeeType}/{@see
 * ManagementFeeBase}/{@see ManagementFeeFlatCadence}). The applicator (PR 2.3)
 * consumes this — never the raw array — so a bad fee can't be promoted into
 * locked config. Mirrors {@see FeePeriodContext}'s readonly-VO style.
 */
final readonly class FeeClauseParams
{
    public function __construct(
        public ManagementFeeType $type,
        public float $value,
        public ?ManagementFeeBase $base,
        public ?ManagementFeeFlatCadence $cadence,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     *
     * @throws InvalidArgumentException when the params can't form a valid fee.
     */
    public static function fromParams(array $params): self
    {
        $type = ManagementFeeType::tryFrom((string) ($params['type'] ?? ''));
        if ($type === null || $type === ManagementFeeType::None) {
            throw new InvalidArgumentException('Fee clause requires a fee type of percentage or flat.');
        }

        $value = (float) ($params['value'] ?? 0);
        if ($value < 0) {
            throw new InvalidArgumentException('Fee value must be non-negative.');
        }

        if ($type === ManagementFeeType::Percentage) {
            if ($value > ManagementFeeCalculator::MAX_PERCENTAGE) {
                throw new InvalidArgumentException('Percentage fee cannot exceed 100.');
            }

            return new self(
                $type,
                $value,
                ManagementFeeBase::tryFrom((string) ($params['base'] ?? '')) ?? ManagementFeeBase::Collected,
                null,
            );
        }

        return new self(
            $type,
            $value,
            null,
            ManagementFeeFlatCadence::tryFrom((string) ($params['flat_cadence'] ?? '')) ?? ManagementFeeFlatCadence::PerPeriod,
        );
    }

    /** Plain-English summary for the rendered clause body (e.g. "8% of rent collected"). */
    public function describe(): string
    {
        if ($this->type === ManagementFeeType::Percentage) {
            return $this->trimZeros($this->value).'% of rent '.($this->base ?? ManagementFeeBase::Collected)->value;
        }

        $amount = 'KES '.number_format($this->value, 0);

        return $this->cadence === ManagementFeeFlatCadence::PerUnit
            ? 'a flat '.$amount.' per occupied unit'
            : 'a flat '.$amount.' per period';
    }

    private function trimZeros(float $value): string
    {
        $formatted = number_format($value, 2);

        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }
}
