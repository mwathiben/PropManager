<?php

declare(strict_types=1);

namespace Tests\Unit\Agreements;

use App\Models\Clause;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Slice-2 PR-2.1: the clause body is a template with {param} placeholders filled
 * from the agreement-clause's chosen params. Rendering is pure so the composed
 * legal text is deterministic and unit-testable; an unknown placeholder is left
 * intact rather than silently blanked (a blanked legal term is worse than an
 * obvious unfilled one).
 */
class ClauseRenderTest extends TestCase
{
    public function test_it_substitutes_known_placeholders(): void
    {
        $out = Clause::renderTemplate(
            'The Manager earns {fee_description} on the Owner portfolio each period.',
            ['fee_description' => '8% of rent collected'],
        );

        $this->assertSame('The Manager earns 8% of rent collected on the Owner portfolio each period.', $out);
    }

    public function test_it_leaves_unknown_placeholders_intact(): void
    {
        $out = Clause::renderTemplate('Notice period is {notice_days} days.', []);

        $this->assertSame('Notice period is {notice_days} days.', $out);
    }

    #[DataProvider('coercibleValues')]
    public function test_it_casts_scalar_params_to_string(mixed $value, string $expected): void
    {
        $this->assertSame("Value: {$expected}.", Clause::renderTemplate('Value: {v}.', ['v' => $value]));
    }

    public static function coercibleValues(): array
    {
        return [
            'int' => [8, '8'],
            'float' => [7.5, '7.5'],
            'string' => ['flat', 'flat'],
        ];
    }
}
