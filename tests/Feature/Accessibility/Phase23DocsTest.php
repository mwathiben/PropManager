<?php

declare(strict_types=1);

namespace Tests\Feature\Accessibility;

use Tests\TestCase;

/**
 * Phase-23 A11Y-DOC-1 + A11Y-DOC-2 watchdogs. The conformance
 * statement and the testing runbook are the procurement-facing
 * artefacts — these pin that they exist and cover their load-bearing
 * sections so a future edit cannot quietly gut them.
 */
class Phase23DocsTest extends TestCase
{
    private function statement(): string
    {
        $path = base_path('docs/runbooks/accessibility.md');
        $this->assertFileExists($path, 'A11Y-DOC-1: docs/runbooks/accessibility.md must exist.');

        return file_get_contents($path);
    }

    public function test_accessibility_conformance_statement_exists(): void
    {
        $doc = $this->statement();

        $this->assertStringContainsString(
            'WCAG 2.1',
            $doc,
            'A11Y-DOC-1: the conformance statement must name its WCAG 2.1 AA target.',
        );
        $this->assertStringContainsString(
            'Conformance status',
            $doc,
            'A11Y-DOC-1: the statement must include the criterion-by-criterion status table.',
        );
        $this->assertStringContainsString(
            'Known gaps',
            $doc,
            'A11Y-DOC-1: the statement must include a known-gaps section.',
        );
        // The criterion table must actually map criteria.
        foreach (['1.3.1', '2.4.1', '4.1.3'] as $criterion) {
            $this->assertStringContainsString(
                $criterion,
                $doc,
                "A11Y-DOC-1: the status table must map WCAG criterion {$criterion}.",
            );
        }
    }

    public function test_a11y_testing_runbook_documents_manual_checklist(): void
    {
        $doc = $this->statement();

        $this->assertStringContainsString(
            'keyboard-only pass',
            $doc,
            'A11Y-DOC-2: the runbook must document the manual keyboard-only checklist.',
        );
        $this->assertStringContainsString(
            'screen-reader pass',
            $doc,
            'A11Y-DOC-2: the runbook must document the manual screen-reader checklist.',
        );
        $this->assertStringContainsString(
            'NVDA',
            $doc,
            'A11Y-DOC-2: the screen-reader checklist must name the tools (NVDA / VoiceOver).',
        );
        $this->assertStringContainsString(
            'When a manual pass is required',
            $doc,
            'A11Y-DOC-2: the runbook must state when a manual pass is required.',
        );
    }
}
