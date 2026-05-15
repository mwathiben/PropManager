<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use Tests\TestCase;

/**
 * Phase-24 I18N-DOC-1 watchdog: the contributor runbook stays in place
 * AND covers the two-engine model + the Swahili term glossary.
 * Documentation rots silently — a future PR that removes the file or
 * the key sections breaks this test.
 */
class Phase24DocsTest extends TestCase
{
    public function test_i18n_runbook_exists_and_covers_the_two_engine_model(): void
    {
        $path = base_path('docs/runbooks/i18n.md');
        $this->assertFileExists($path, 'I18N-DOC-1: docs/runbooks/i18n.md must exist.');

        $contents = file_get_contents($path);

        // The two-engine model is the must-know for any contributor —
        // it MUST stay documented.
        $this->assertStringContainsString(
            'two-engine',
            strtolower($contents),
            'I18N-DOC-1: runbook must explain the two-engine model (PHP __() vs vue-i18n).',
        );
        $this->assertStringContainsString(
            'useI18n',
            $contents,
            'I18N-DOC-1: runbook must reference the useI18n composable.',
        );
        $this->assertStringContainsString(
            'lang/{locale}.json',
            $contents,
            'I18N-DOC-1: runbook must document the JSON bundle path.',
        );
        $this->assertStringContainsString(
            'lang/{locale}/*.php',
            $contents,
            'I18N-DOC-1: runbook must document the PHP lang tree path.',
        );
    }

    public function test_i18n_runbook_includes_swahili_term_glossary(): void
    {
        $contents = file_get_contents(base_path('docs/runbooks/i18n.md'));

        // The glossary keeps translations consistent — every future
        // translator should be able to look up "invoice" and find
        // "ankara", not invent a new term. These are the entries we
        // EXPECT to be present (per qualityStandards.translation_quality
        // decisions baselined in the Phase-24 PRD).
        $glossary = [
            'ankara',          // invoice
            'mpangaji',        // tenant
            'mwenye nyumba',   // landlord
            'mlinzi',          // caretaker
            'kodi',            // rent
            'M-Pesa',          // brand — never translate
        ];

        foreach ($glossary as $term) {
            $this->assertStringContainsString(
                $term,
                $contents,
                "I18N-DOC-1: runbook glossary must define '{$term}'.",
            );
        }
    }

    public function test_i18n_runbook_has_honest_scope_section(): void
    {
        $contents = file_get_contents(base_path('docs/runbooks/i18n.md'));

        // An honest scope-boundary statement is what stops a reader
        // from assuming "PropManager is fully Swahili" — which it is
        // not, by design.
        $this->assertStringContainsString(
            'scope',
            strtolower($contents),
            'I18N-DOC-1: runbook must include an honest scope statement.',
        );
        $this->assertStringContainsString(
            'long tail',
            strtolower($contents),
            'I18N-DOC-1: runbook must call out the per-page-strings long tail that is still English.',
        );
    }
}
