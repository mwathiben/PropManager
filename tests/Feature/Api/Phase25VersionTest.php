<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

/**
 * Phase-25 API-VERSION-1 watchdog: the deprecation contract runbook
 * stays in place AND covers the URL-versioning + Sunset + 6-month
 * contract that consumers will rely on.
 */
class Phase25VersionTest extends TestCase
{
    public function test_api_deprecation_runbook_exists(): void
    {
        $path = base_path('docs/runbooks/api-deprecation.md');
        $this->assertFileExists($path, 'API-VERSION-1: docs/runbooks/api-deprecation.md must exist.');
    }

    public function test_runbook_covers_the_six_month_contract(): void
    {
        $contents = file_get_contents(base_path('docs/runbooks/api-deprecation.md'));

        $this->assertStringContainsString(
            '6 months',
            $contents,
            'API-VERSION-1: runbook must commit to a minimum 6-month deprecation window.',
        );
        $this->assertStringContainsString(
            'Sunset',
            $contents,
            'API-VERSION-1: runbook must reference the Sunset header (RFC 8594).',
        );
        $this->assertStringContainsString(
            'Deprecation',
            $contents,
            'API-VERSION-1: runbook must reference the Deprecation header.',
        );
        $this->assertStringContainsString(
            '/api/v1/',
            $contents,
            'API-VERSION-1: runbook must document URL-prefix major versioning.',
        );
        $this->assertStringContainsString(
            '410',
            $contents,
            'API-VERSION-1: runbook must describe the post-sunset 410 Gone response.',
        );
    }

    public function test_consumer_deprecations_changelog_exists(): void
    {
        $path = base_path('docs/api/deprecations.md');
        $this->assertFileExists(
            $path,
            'API-VERSION-3 prep: docs/api/deprecations.md must exist as the consumer-facing changelog (created by VERSION-1; populated as deprecations land).',
        );

        $contents = file_get_contents($path);
        // The schema (route / deprecated_at / sunset_at / replacement)
        // must be documented even when there are no active entries —
        // contributors need to know the format.
        foreach (['deprecated_at', 'sunset_at', 'replacement'] as $field) {
            $this->assertStringContainsString(
                $field,
                $contents,
                "API-VERSION-1: deprecations log must document the '{$field}' field.",
            );
        }
    }
}
