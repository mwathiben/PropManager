<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Phase-17 MONEY-1/2/3/7: immutable string-decimal money value object
 * backed by bcmath. Replaces the (float) X + (float) Y pattern that
 * accumulates float-precision drift across compounding fees + multi-
 * step payment allocation.
 *
 * Contract:
 *   - Internal representation is a normalised decimal STRING with
 *     fixed scale (2 by default for KES; configurable for currencies
 *     with different minor-unit conventions).
 *   - All arithmetic uses bcmath at scale + 2 (so multiply/divide
 *     can carry intermediate precision) then rounds back to scale
 *     for the final result.
 *   - Rounding is banker's (half-even) by default — financial
 *     standard for cumulative compounding.
 *   - Negative values are PERMITTED (refund / wallet-debit semantics)
 *     but isNegative() / requirePositive() are available for the
 *     callsites that must reject them.
 *
 * Not yet implemented (out of scope for Phase 17): currency-pair
 * conversion (MONEY-9 is deferred at PRD level — Lease.currency=USD
 * etc. are rejected at the validator instead).
 */
final class Money implements Stringable
{
    private function __construct(
        private readonly string $value,
        private readonly int $scale,
    ) {}

    public static function fromString(string $value, int $scale = 2): self
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Money::fromString rejected non-numeric input: '{$value}'");
        }
        if ($scale < 0 || $scale > 10) {
            throw new InvalidArgumentException("Money scale must be in [0,10], got {$scale}");
        }

        // Reject scientific notation explicitly — bcmath silently treats
        // '1e3' as '1' otherwise.
        if (preg_match('/[eE]/', $value)) {
            throw new InvalidArgumentException("Money::fromString rejected scientific-notation input: '{$value}'");
        }

        return new self(self::normalize($value, $scale), $scale);
    }

    public static function fromMinorUnits(int $cents, int $scale = 2): self
    {
        $divisor = bcpow('10', (string) $scale, $scale);
        $value = bcdiv((string) $cents, $divisor, $scale);

        return new self($value, $scale);
    }

    public static function zero(int $scale = 2): self
    {
        return new self(self::normalize('0', $scale), $scale);
    }

    public function add(self $other): self
    {
        $this->assertCompatible($other);

        return new self(self::normalize(bcadd($this->value, $other->value, $this->scale), $this->scale), $this->scale);
    }

    public function subtract(self $other): self
    {
        $this->assertCompatible($other);

        return new self(self::normalize(bcsub($this->value, $other->value, $this->scale), $this->scale), $this->scale);
    }

    /**
     * Multiply by a scalar (e.g., percentage as decimal: 0.05 for 5%).
     * The factor is a numeric string so callers preserve precision.
     */
    public function multiply(string $factor): self
    {
        if (! is_numeric($factor)) {
            throw new InvalidArgumentException("Money::multiply rejected non-numeric factor: '{$factor}'");
        }

        // Compute at higher precision then round half-even back to scale.
        $intermediate = bcmul($this->value, $factor, $this->scale + 4);

        return new self($this->roundHalfEven($intermediate, $this->scale), $this->scale);
    }

    public function divide(string $divisor): self
    {
        if (! is_numeric($divisor) || bccomp($divisor, '0', 10) === 0) {
            throw new InvalidArgumentException("Money::divide rejected divisor: '{$divisor}'");
        }

        $intermediate = bcdiv($this->value, $divisor, $this->scale + 4);

        return new self($this->roundHalfEven($intermediate, $this->scale), $this->scale);
    }

    public function negate(): self
    {
        return new self(self::normalize(bcmul($this->value, '-1', $this->scale), $this->scale), $this->scale);
    }

    public function compareTo(self $other): int
    {
        $this->assertCompatible($other);

        return bccomp($this->value, $other->value, $this->scale);
    }

    public function equals(self $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    public function greaterThan(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function lessThan(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isZero(): bool
    {
        return bccomp($this->value, '0', $this->scale) === 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->value, '0', $this->scale) < 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->value, '0', $this->scale) > 0;
    }

    /**
     * Clamp negative values to zero. Useful for "outstanding amount"
     * semantics where over-payment shouldn't surface as a negative
     * receivable.
     */
    public function clampPositive(): self
    {
        return $this->isNegative() ? self::zero($this->scale) : $this;
    }

    /**
     * Pick the larger of two amounts.
     */
    public function max(self $other): self
    {
        return $this->compareTo($other) >= 0 ? $this : $other;
    }

    /**
     * Pick the smaller of two amounts.
     */
    public function min(self $other): self
    {
        return $this->compareTo($other) <= 0 ? $this : $other;
    }

    public function toDecimalString(): string
    {
        return $this->value;
    }

    public function toMinorUnits(): int
    {
        // For amounts within DECIMAL(10,2) range the multiplied value fits
        // comfortably in 63 bits (10^10 < 2^33; * 10^scale up to 10^12 < 2^40).
        $multiplier = bcpow('10', (string) $this->scale, 0);
        $scaled = bcmul($this->value, $multiplier, 0);

        return (int) $scaled;
    }

    /**
     * Explicit named-method conversion to float for legacy interop. The
     * name is deliberately verbose so call-sites read as "I know this
     * is lossy."
     */
    public function toFloatLossy(): float
    {
        return (float) $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function assertCompatible(self $other): void
    {
        if ($other->scale !== $this->scale) {
            throw new InvalidArgumentException(
                "Money scale mismatch: {$this->scale} vs {$other->scale}"
            );
        }
    }

    private static function normalize(string $value, int $scale): string
    {
        // bcadd by 0 forces canonical scale representation.
        return bcadd($value, '0', $scale);
    }

    /**
     * Banker's rounding (half-even). PHP's round() with PHP_ROUND_HALF_EVEN
     * works on floats — to stay in string-decimal land we implement it
     * via bcmath.
     */
    private function roundHalfEven(string $value, int $scale): string
    {
        $padded = bcadd($value, '0', $scale + 1);
        $marker = strpos($padded, '.');

        if ($marker === false) {
            return bcadd($value, '0', $scale);
        }

        $lastDigit = substr($padded, -1);
        $truncated = substr($padded, 0, -1);

        if ($lastDigit === '0' || $lastDigit === '') {
            return bcadd($truncated, '0', $scale);
        }

        if ((int) $lastDigit < 5) {
            return bcadd($truncated, '0', $scale);
        }

        if ((int) $lastDigit > 5) {
            // Round up (or down for negatives — bcmath handles sign).
            $increment = bccomp($truncated, '0', $scale) < 0 ? '-1' : '1';
            $smallest = bcdiv($increment, bcpow('10', (string) $scale, 0), $scale);

            return bcadd($truncated, $smallest, $scale);
        }

        // lastDigit === '5' — check if digit-before is even or odd.
        $rounded = bcadd($truncated, '0', $scale);
        $beforeRoundDigit = $rounded === '' ? '0' : substr($rounded, -1);

        if ((int) $beforeRoundDigit % 2 === 0) {
            return $rounded;
        }

        $increment = bccomp($truncated, '0', $scale) < 0 ? '-1' : '1';
        $smallest = bcdiv($increment, bcpow('10', (string) $scale, 0), $scale);

        return bcadd($truncated, $smallest, $scale);
    }
}
