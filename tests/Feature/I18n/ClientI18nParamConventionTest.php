<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use Tests\TestCase;

/**
 * Phase-66 BUILD-INTEGRITY-2: translation keys consumed in .vue
 * components are rendered by vue-i18n, which interpolates {curly}
 * placeholders — NOT Laravel's :colon style. A :colon placeholder in a
 * key a Vue component calls with object args renders the literal
 * ":param" string to the user (the value is passed through untouched).
 *
 * This was discovered during Phase 66: every client-consumed
 * parameterised key living in a lang/<locale>/*.php namespace file used
 * :colon and silently rendered broken. This guard pins the fixed keys
 * so the regression cannot silently return — add a row here whenever a
 * Vue component starts calling t(key, { ... }) with a new key.
 */
class ClientI18nParamConventionTest extends TestCase
{
    public function test_client_consumed_param_keys_use_curly_placeholders(): void
    {
        // key => [param names passed from the Vue t(key, { ... }) call site]
        $clientParamKeys = [
            'accounting.export.accounts_configured' => ['count'],
            'accounting.export.invoice_types_unmapped' => ['count'],
            'accounting.export.expense_categories_unmapped' => ['count'],
            'onboarding.resume_banner.title' => ['current', 'total'],
            'onboarding.resume_banner.subtitle' => ['pct'],
            'onboarding.tour.step_of' => ['current', 'total'],
            'growth.leaderboard.your_position' => ['rank'],
            'growth.leaderboard.score' => ['score'],
            'growth.leaderboard.points' => ['score'],
            'growth.leaderboard.breakdown' => ['attributed', 'rewarded'],
            'growth.leaderboard.ops_subtitle' => ['total'],
            'growth.cohort.subtitle' => ['months'],
            'growth.cohort.month_offset' => ['offset'],
            'growth.cohort.insufficient_sample' => ['min'],
        ];

        foreach (['en', 'sw'] as $locale) {
            foreach ($clientParamKeys as $key => $params) {
                $value = trans($key, [], $locale);

                $this->assertIsString($value);
                $this->assertNotSame($key, $value, "Missing translation for {$key} [{$locale}]");

                foreach ($params as $param) {
                    $this->assertStringContainsString(
                        '{'.$param.'}',
                        $value,
                        "[{$locale}] {$key} must use the vue-i18n {{$param}} placeholder so the client interpolates it"
                    );
                    $this->assertStringNotContainsString(
                        ':'.$param,
                        $value,
                        "[{$locale}] {$key} still uses Laravel :{$param} — vue-i18n renders that literally"
                    );
                }
            }
        }
    }
}
