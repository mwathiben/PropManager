<?php

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\Payment\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_creates_from_float(): void
    {
        $money = Money::fromFloat(100.50, 'KES');

        $this->assertEquals(10050, $money->amount);
        $this->assertEquals('KES', $money->currency);
    }

    public function test_creates_from_smallest_unit(): void
    {
        $money = Money::fromSmallestUnit(10050, 'USD');

        $this->assertEquals(10050, $money->amount);
        $this->assertEquals('USD', $money->currency);
    }

    public function test_converts_to_float(): void
    {
        $money = Money::fromFloat(100.50);

        $this->assertEquals(100.50, $money->toFloat());
    }

    public function test_converts_to_smallest_unit(): void
    {
        $money = Money::fromFloat(100.50);

        $this->assertEquals(10050, $money->toSmallestUnit());
    }

    public function test_converts_to_paystack_amount(): void
    {
        $money = Money::fromFloat(100.50);

        $this->assertEquals(10050, $money->toPaystackAmount());
    }

    public function test_converts_to_mpesa_amount(): void
    {
        $money = Money::fromFloat(100.50);

        $this->assertEquals(101, $money->toMpesaAmount());
    }

    public function test_formats_currency(): void
    {
        $money = Money::fromFloat(100.50, 'KES');

        $this->assertEquals('KES 100.50', $money->format());
    }

    public function test_normalizes_currency_to_uppercase(): void
    {
        $money = Money::fromFloat(100, 'kes');

        $this->assertEquals('KES', $money->currency);
    }

    public function test_defaults_to_kes_currency(): void
    {
        $money = Money::fromFloat(100);

        $this->assertEquals('KES', $money->currency);
    }

    public function test_throws_for_negative_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount cannot be negative');

        new Money(-100);
    }

    public function test_handles_zero_amount(): void
    {
        $money = Money::fromFloat(0);

        $this->assertEquals(0, $money->amount);
        $this->assertEquals(0.0, $money->toFloat());
    }

    public function test_handles_large_amounts(): void
    {
        $money = Money::fromFloat(1000000.99);

        $this->assertEquals(100000099, $money->amount);
        $this->assertEquals(1000000.99, $money->toFloat());
    }

    public function test_rounds_to_nearest_cent(): void
    {
        $money = Money::fromFloat(100.555);

        $this->assertEquals(10056, $money->amount);
    }
}
