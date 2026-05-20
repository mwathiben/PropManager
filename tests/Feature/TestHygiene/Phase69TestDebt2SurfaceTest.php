<?php

declare(strict_types=1);

namespace Tests\Feature\TestHygiene;

use Tests\TestCase;

/**
 * Phase-69 TEST-DEBT-2 CI (CI-1): cross-cutting surface map. Guards the
 * deprecation gate, the two hygiene/observability guards, the coverage
 * floor, and the convention docs against drift.
 */
class Phase69TestDebt2SurfaceTest extends TestCase
{
    private function read(string $relative): string
    {
        $path = base_path($relative);
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_phpunit_deprecation_gate_is_configured(): void
    {
        $xml = $this->read('phpunit.xml');
        $this->assertStringContainsString('failOnPhpunitDeprecation="true"', $xml);
        $this->assertStringContainsString('failOnPhpunitWarning="true"', $xml);
    }

    public function test_guards_exist(): void
    {
        $this->assertTrue(class_exists(\Tests\Feature\TestHygiene\Phase69MetadataHygieneTest::class));
        $this->assertTrue(class_exists(\Tests\Feature\Observability\Phase69GaugeNamingTest::class));
    }

    public function test_convention_docs_exist(): void
    {
        $this->assertFileExists(base_path('docs/runbooks/testing.md'));
        $this->assertFileExists(base_path('docs/runbooks/metrics-naming.md'));
        $this->assertStringContainsString('Coverage floor', $this->read('docs/runbooks/testing.md'));
    }

    public function test_ci_enforces_a_coverage_floor(): void
    {
        $this->assertMatchesRegularExpression('/--min=\d+/', $this->read('.github/workflows/ci.yml'));
    }

    public function test_converted_files_use_attributes_not_doc_comment_metadata(): void
    {
        $concurrent = $this->read('tests/Feature/ConcurrentWebhookTest.php');
        $this->assertStringContainsString('#[Group(', $concurrent);
        $this->assertStringNotContainsString('* @group', $concurrent);

        foreach (['tests/Feature/Reports/Phase27GoldenQueriesTest.php', 'tests/Feature/Reports/Phase27PerfTest.php'] as $file) {
            $src = $this->read($file);
            $this->assertStringContainsString('#[DataProvider(', $src);
            $this->assertStringNotContainsString('@dataProvider', $src);
        }
    }
}
