<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use Tests\TestCase;

/**
 * Phase-44 ESLINT-CUSTOM-3: source-level watchdog for the two inline
 * ESLint rules (no-hardcoded-english-strings + no-ltr-class) shipped
 * in Phase-44 ESLINT-CUSTOM-1/-2. The rules give devs in-IDE feedback
 * complementing the Phase-43 HardcodedEnglishScanner PHP-side ratchet
 * and the Phase-44 RTL-MIGRATE-1 codemod sweep — a future eslint
 * config refactor must not silently drop them.
 *
 * Follows the Phase-18/19/22/23/24/43 source-grep watchdog pattern.
 */
class Phase44EslintCustomTest extends TestCase
{
    public function test_eslint_config_registers_propmanager_plugin(): void
    {
        $config = file_get_contents(base_path('eslint.config.js'));

        $this->assertStringContainsString(
            "'propmanager': propManagerPlugin",
            $config,
            'ESLINT-CUSTOM-3: eslint.config.js must register the propmanager plugin.',
        );
    }

    public function test_no_hardcoded_english_rule_is_defined_and_enabled(): void
    {
        $config = file_get_contents(base_path('eslint.config.js'));

        $this->assertStringContainsString(
            "'no-hardcoded-english-strings'",
            $config,
            'ESLINT-CUSTOM-3: no-hardcoded-english-strings rule must be defined.',
        );
        $this->assertMatchesRegularExpression(
            "/'propmanager\/no-hardcoded-english-strings':\s*'(warn|error)'/",
            $config,
            'ESLINT-CUSTOM-3: no-hardcoded-english-strings must be enabled (warn or error).',
        );
    }

    public function test_no_ltr_class_rule_is_defined_and_enabled(): void
    {
        $config = file_get_contents(base_path('eslint.config.js'));

        $this->assertStringContainsString(
            "'no-ltr-class'",
            $config,
            'ESLINT-CUSTOM-3: no-ltr-class rule must be defined.',
        );
        $this->assertMatchesRegularExpression(
            "/'propmanager\/no-ltr-class':\s*'(warn|error)'/",
            $config,
            'ESLINT-CUSTOM-3: no-ltr-class must be enabled (warn or error).',
        );
    }

    public function test_rules_use_template_body_visitor(): void
    {
        $config = file_get_contents(base_path('eslint.config.js'));

        $this->assertStringContainsString(
            'defineTemplateBodyVisitor',
            $config,
            'ESLINT-CUSTOM-3: rules must use vue-eslint-parser defineTemplateBodyVisitor to walk template AST.',
        );
    }
}
