<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ManagementFee;

use App\Services\ManagementFee\FeePeriodContext;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FeePeriodContextTest extends TestCase
{
    public function test_valid_context_constructs_without_error(): void
    {
        $ctx = new FeePeriodContext(collected: 1000.0, billed: 1200.0, scheduled: 1100.0, occupiedUnits: 3);

        $this->assertSame(1000.0, $ctx->collected);
        $this->assertSame(1200.0, $ctx->billed);
        $this->assertSame(1100.0, $ctx->scheduled);
        $this->assertSame(3, $ctx->occupiedUnits);
    }

    public function test_zero_values_are_valid(): void
    {
        $ctx = new FeePeriodContext(collected: 0.0, billed: 0.0, scheduled: 0.0, occupiedUnits: 0);

        $this->assertSame(0.0, $ctx->collected);
        $this->assertSame(0, $ctx->occupiedUnits);
    }

    #[DataProvider('negativeValueProvider')]
    public function test_negative_values_throw_invalid_argument_exception(
        float $collected,
        float $billed,
        float $scheduled,
        int $occupiedUnits
    ): void {
        $this->expectException(\InvalidArgumentException::class);

        new FeePeriodContext(
            collected: $collected,
            billed: $billed,
            scheduled: $scheduled,
            occupiedUnits: $occupiedUnits,
        );
    }

    /** @return array<string, array{float, float, float, int}> */
    public static function negativeValueProvider(): array
    {
        return [
            'negative collected' => [-1.0, 0.0, 0.0, 0],
            'negative billed' => [0.0, -0.01, 0.0, 0],
            'negative scheduled' => [0.0, 0.0, -500.0, 0],
            'negative occupiedUnits' => [0.0, 0.0, 0.0, -1],
        ];
    }
}
