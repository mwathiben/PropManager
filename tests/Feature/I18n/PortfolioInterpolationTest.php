<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use Tests\TestCase;

/**
 * Portfolio KPI subtitles are rendered by vue-i18n (hydrated from the
 * Inertia-shared `i18n` prop), which interpolates `{param}` — NOT Laravel's
 * `:param`. These strings shipped with `:occupied` / `:total` / `:buildings`
 * and rendered raw on the post-onboarding dashboard. Guard the correct syntax.
 */
class PortfolioInterpolationTest extends TestCase
{
    public function test_portfolio_subtitles_use_vue_i18n_brace_interpolation(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            $portfolio = require base_path("lang/{$locale}/portfolio.php");

            $subtitles = [
                "{$locale}.kpi.units_subtitle" => $portfolio['kpi']['units_subtitle'],
                "{$locale}.kpi.properties_subtitle" => $portfolio['kpi']['properties_subtitle'],
                "{$locale}.card.units" => $portfolio['card']['units'],
            ];

            foreach ($subtitles as $key => $string) {
                foreach ([':occupied', ':total', ':buildings'] as $laravelStyle) {
                    $this->assertStringNotContainsString(
                        $laravelStyle,
                        $string,
                        "{$key} uses Laravel `{$laravelStyle}` syntax; vue-i18n needs `{...}` and won't interpolate it.",
                    );
                }
                $this->assertStringContainsString('{', $string, "{$key} should carry a {param} placeholder.");
            }
        }
    }
}
