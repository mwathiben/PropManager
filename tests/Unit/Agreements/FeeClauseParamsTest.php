<?php

declare(strict_types=1);

namespace Tests\Unit\Agreements;

use App\Enums\ManagementFeeBase;
use App\Enums\ManagementFeeFlatCadence;
use App\Enums\ManagementFeeType;
use App\Services\ManagementFee\FeeClauseParams;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Slice-2 PR-2.1: the fail-closed boundary that turns a fee clause's loose JSON
 * params into the typed fee model the applicator (PR 2.3) locks onto config.
 */
class FeeClauseParamsTest extends TestCase
{
    public function test_percentage_params_parse_and_describe(): void
    {
        $params = FeeClauseParams::fromParams(['type' => 'percentage', 'value' => 8, 'base' => 'collected']);

        $this->assertSame(ManagementFeeType::Percentage, $params->type);
        $this->assertSame(8.0, $params->value);
        $this->assertSame(ManagementFeeBase::Collected, $params->base);
        $this->assertNull($params->cadence);
        $this->assertSame('8% of rent collected', $params->describe());
    }

    public function test_percentage_defaults_base_to_collected(): void
    {
        $params = FeeClauseParams::fromParams(['type' => 'percentage', 'value' => 7.5]);

        $this->assertSame(ManagementFeeBase::Collected, $params->base);
        $this->assertSame('7.5% of rent collected', $params->describe());
    }

    public function test_flat_per_unit_parses_and_describes(): void
    {
        $params = FeeClauseParams::fromParams(['type' => 'flat', 'value' => 5000, 'flat_cadence' => 'per_unit']);

        $this->assertSame(ManagementFeeType::Flat, $params->type);
        $this->assertSame(ManagementFeeFlatCadence::PerUnit, $params->cadence);
        $this->assertNull($params->base);
        $this->assertSame('a flat KES 5,000 per occupied unit', $params->describe());
    }

    public function test_flat_defaults_cadence_to_per_period(): void
    {
        $params = FeeClauseParams::fromParams(['type' => 'flat', 'value' => 5000]);

        $this->assertSame(ManagementFeeFlatCadence::PerPeriod, $params->cadence);
        $this->assertSame('a flat KES 5,000 per period', $params->describe());
    }

    public function test_it_rejects_a_missing_or_none_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FeeClauseParams::fromParams(['value' => 8]);
    }

    public function test_it_rejects_a_percentage_over_one_hundred(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FeeClauseParams::fromParams(['type' => 'percentage', 'value' => 150]);
    }

    public function test_it_rejects_a_negative_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FeeClauseParams::fromParams(['type' => 'flat', 'value' => -1]);
    }
}
