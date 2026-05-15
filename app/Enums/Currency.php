<?php

namespace App\Enums;

enum Currency: string
{
    case KES = 'KES';
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';

    public function label(): string
    {
        return match ($this) {
            self::KES => 'Kenyan Shilling',
            self::USD => 'US Dollar',
            self::EUR => 'Euro',
            self::GBP => 'British Pound',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::KES => 'KSh',
            self::USD => '$',
            self::EUR => '€',
            self::GBP => '£',
        };
    }

    public function country(): string
    {
        return match ($this) {
            self::KES => 'Kenya',
            self::USD => 'United States',
            self::EUR => 'Eurozone',
            self::GBP => 'United Kingdom',
        };
    }

    public function locale(): string
    {
        return match ($this) {
            self::KES => 'en-KE',
            self::USD => 'en-US',
            self::EUR => 'de-DE',
            self::GBP => 'en-GB',
        };
    }

    /**
     * Phase-24 I18N-FORMAT-2: BCP-47 tag for the currency *and* the
     * active application locale. When the user switches PropManager to
     * Swahili, KES-denominated values should also format with the
     * sw-KE tag (which yields a localised separator + currency name).
     * Currencies without a registered Swahili variant fall back to the
     * default locale() result.
     */
    public function localeFor(?string $appLocale): string
    {
        if ($appLocale === 'sw' && $this === self::KES) {
            return 'sw-KE';
        }

        return $this->locale();
    }

    public function minorUnitMultiplier(): int
    {
        return 100;
    }

    public function toMinorUnits(float $amount): int
    {
        return (int) round($amount * $this->minorUnitMultiplier());
    }

    /**
     * Phase-17 MONEY-3: canonical bcmath-backed conversion. Preferred
     * over toMinorUnits(float) wherever the caller has a Money instance —
     * round-trips precisely and uses banker's-rounding to disagree with
     * Paystack/M-Pesa minor-unit refund-reconciliation by zero cents.
     *
     * The float variant remains for backwards compatibility but should
     * not be added to new code.
     */
    public function toMinorUnitsFromMoney(\App\ValueObjects\Money $amount): int
    {
        return $amount->toMinorUnits();
    }

    public function fromMinorUnits(int $amount): float
    {
        return $amount / $this->minorUnitMultiplier();
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn ($case) => [
            'value' => $case->value,
            'label' => "{$case->value} - {$case->label()} ({$case->country()})",
        ])->all();
    }

    public static function default(): self
    {
        return self::KES;
    }
}
