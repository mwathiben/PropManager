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
}
