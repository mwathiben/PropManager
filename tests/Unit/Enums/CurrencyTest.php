<?php

namespace Tests\Unit\Enums;

use App\Enums\Currency;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    #[Test]
    public function has_four_cases(): void
    {
        $cases = Currency::cases();

        $this->assertCount(4, $cases);
        $this->assertSame('KES', Currency::KES->value);
        $this->assertSame('USD', Currency::USD->value);
        $this->assertSame('EUR', Currency::EUR->value);
        $this->assertSame('GBP', Currency::GBP->value);
    }

    #[Test]
    public function label_returns_human_readable_name(): void
    {
        $this->assertSame('Kenyan Shilling', Currency::KES->label());
        $this->assertSame('US Dollar', Currency::USD->label());
        $this->assertSame('Euro', Currency::EUR->label());
        $this->assertSame('British Pound', Currency::GBP->label());
    }

    #[Test]
    public function symbol_returns_currency_symbol(): void
    {
        $this->assertSame('KSh', Currency::KES->symbol());
        $this->assertSame('$', Currency::USD->symbol());
        $this->assertSame('€', Currency::EUR->symbol());
        $this->assertSame('£', Currency::GBP->symbol());
    }

    #[Test]
    public function country_returns_country_name(): void
    {
        $this->assertSame('Kenya', Currency::KES->country());
        $this->assertSame('United States', Currency::USD->country());
        $this->assertSame('Eurozone', Currency::EUR->country());
        $this->assertSame('United Kingdom', Currency::GBP->country());
    }

    #[Test]
    public function minor_unit_multiplier_returns_100(): void
    {
        foreach (Currency::cases() as $currency) {
            $this->assertSame(100, $currency->minorUnitMultiplier());
        }
    }

    #[Test]
    public function values_returns_string_array(): void
    {
        $values = Currency::values();

        $this->assertSame(['KES', 'USD', 'EUR', 'GBP'], $values);
    }

    #[Test]
    public function options_returns_value_label_pairs(): void
    {
        $options = Currency::options();

        $this->assertCount(4, $options);
        $this->assertSame('KES', $options[0]['value']);
        $this->assertSame('KES - Kenyan Shilling (Kenya)', $options[0]['label']);
    }

    #[Test]
    public function default_returns_kes(): void
    {
        $this->assertSame(Currency::KES, Currency::default());
    }

    #[Test]
    public function locale_returns_locale_string(): void
    {
        $this->assertSame('en-KE', Currency::KES->locale());
        $this->assertSame('en-US', Currency::USD->locale());
        $this->assertSame('de-DE', Currency::EUR->locale());
        $this->assertSame('en-GB', Currency::GBP->locale());
    }

    #[Test]
    public function to_minor_units_converts_correctly_for_all_currencies(): void
    {
        foreach (Currency::cases() as $currency) {
            $this->assertSame(10050, $currency->toMinorUnits(100.50), "{$currency->value} failed");
            $this->assertSame(500000, $currency->toMinorUnits(5000.00), "{$currency->value} failed");
            $this->assertSame(100, $currency->toMinorUnits(1.00), "{$currency->value} failed");
            $this->assertSame(0, $currency->toMinorUnits(0.00), "{$currency->value} failed");
        }
    }

    #[Test]
    public function from_minor_units_converts_correctly_for_all_currencies(): void
    {
        foreach (Currency::cases() as $currency) {
            $this->assertSame(100.5, $currency->fromMinorUnits(10050), "{$currency->value} failed");
            $this->assertSame(5000.0, $currency->fromMinorUnits(500000), "{$currency->value} failed");
            $this->assertSame(1.0, $currency->fromMinorUnits(100), "{$currency->value} failed");
            $this->assertSame(0.0, $currency->fromMinorUnits(0), "{$currency->value} failed");
        }
    }

    #[Test]
    public function to_minor_units_rounds_half_cents(): void
    {
        $currency = Currency::KES;

        $this->assertSame(10056, $currency->toMinorUnits(100.555));
        $this->assertSame(10055, $currency->toMinorUnits(100.554));
        $this->assertSame(10055, $currency->toMinorUnits(100.5549));
    }

    #[Test]
    public function from_minor_units_returns_float(): void
    {
        $result = Currency::USD->fromMinorUnits(1);

        $this->assertSame(0.01, $result);
        $this->assertIsFloat($result);
    }
}
