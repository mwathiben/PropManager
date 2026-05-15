<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Dedoc\Scramble\Generator;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Phase-25 API-DOC-3 watchdog: the api:changelog command produces
 * structured diff output between the live spec and the checked-in
 * baseline, and the consumer-facing changelog.md skeleton exists with
 * the marker the command appends against.
 */
class Phase25ChangelogTest extends TestCase
{
    public function test_consumer_changelog_skeleton_exists(): void
    {
        $path = base_path('docs/api/changelog.md');
        $this->assertFileExists($path, 'API-DOC-3: docs/api/changelog.md must exist as the consumer-facing changelog.');

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString(
            '<!-- changelog:entries -->',
            $content,
            'API-DOC-3: changelog.md must carry the <!-- changelog:entries --> marker so api:changelog can insert new blocks at a stable anchor.',
        );
    }

    public function test_baseline_spec_exists_and_is_valid_openapi(): void
    {
        $path = base_path('docs/api/openapi-baseline.json');
        $this->assertFileExists($path, 'API-DOC-3: docs/api/openapi-baseline.json must exist so api:changelog has something to diff against.');

        $baseline = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($baseline);
        $this->assertArrayHasKey('openapi', $baseline);
        $this->assertArrayHasKey('paths', $baseline);
        $this->assertGreaterThan(
            10,
            count($baseline['paths']),
            'API-DOC-3: baseline must cover the API surface (expected >10 paths, got '.count($baseline['paths']).').',
        );
    }

    public function test_command_with_unchanged_baseline_reports_no_changes(): void
    {
        $baseline = json_decode((string) file_get_contents(base_path('docs/api/openapi-baseline.json')), true);
        $this->app->instance(Generator::class, new class($baseline)
        {
            public function __construct(private array $spec) {}

            public function __invoke(): array
            {
                return $this->spec;
            }
        });

        Artisan::call('api:changelog');
        $output = Artisan::output();

        $this->assertStringContainsString('No spec changes since baseline', $output);
    }

    public function test_command_surfaces_added_removed_and_changed_paths(): void
    {
        $baseline = [
            'openapi' => '3.1.0',
            'paths' => [
                '/v1/old' => ['get' => ['responses' => ['200' => ['description' => 'ok']]]],
                '/v1/same' => ['get' => ['responses' => ['200' => ['description' => 'ok']]]],
                '/v1/mutating' => ['get' => ['responses' => ['200' => ['description' => 'baseline shape']]]],
            ],
        ];
        $current = [
            'openapi' => '3.1.0',
            'paths' => [
                '/v1/same' => ['get' => ['responses' => ['200' => ['description' => 'ok']]]],
                '/v1/new' => ['get' => ['responses' => ['200' => ['description' => 'ok']]]],
                '/v1/mutating' => ['get' => ['responses' => ['200' => ['description' => 'CHANGED']]]],
            ],
        ];

        $baselinePath = base_path('docs/api/openapi-baseline.json');
        $original = (string) file_get_contents($baselinePath);
        file_put_contents($baselinePath, json_encode($baseline));

        try {
            $this->app->instance(Generator::class, new class($current)
            {
                public function __construct(private array $spec) {}

                public function __invoke(): array
                {
                    return $this->spec;
                }
            });

            Artisan::call('api:changelog');
            $output = Artisan::output();

            $this->assertStringContainsString('/v1/new', $output, 'API-DOC-3: added paths must appear in the Added section.');
            $this->assertStringContainsString('/v1/old', $output, 'API-DOC-3: removed paths must appear in the Removed section.');
            $this->assertStringContainsString('/v1/mutating', $output, 'API-DOC-3: changed paths must appear in the Changed section.');
            $this->assertStringContainsString('Added', $output);
            $this->assertStringContainsString('Removed', $output);
            $this->assertStringContainsString('Changed', $output);
        } finally {
            file_put_contents($baselinePath, $original);
        }
    }

    public function test_commit_flag_rotates_baseline_and_appends_changelog(): void
    {
        $baselinePath = base_path('docs/api/openapi-baseline.json');
        $changelogPath = base_path('docs/api/changelog.md');
        $originalBaseline = (string) file_get_contents($baselinePath);
        $originalChangelog = (string) file_get_contents($changelogPath);

        $current = [
            'openapi' => '3.1.0',
            'paths' => [
                '/v1/freshly-added' => ['get' => ['responses' => ['200' => ['description' => 'ok']]]],
            ],
        ];

        try {
            file_put_contents($baselinePath, json_encode(['openapi' => '3.1.0', 'paths' => []]));
            $this->app->instance(Generator::class, new class($current)
            {
                public function __construct(private array $spec) {}

                public function __invoke(): array
                {
                    return $this->spec;
                }
            });

            Artisan::call('api:changelog', ['--commit' => true, '--date' => '2099-01-01']);

            $changelog = (string) file_get_contents($changelogPath);
            $this->assertStringContainsString('## 2099-01-01', $changelog, 'API-DOC-3: --commit must append a dated block.');
            $this->assertStringContainsString('/v1/freshly-added', $changelog);

            $rotated = json_decode((string) file_get_contents($baselinePath), true);
            $this->assertArrayHasKey('/v1/freshly-added', $rotated['paths'], 'API-DOC-3: --commit must rotate the baseline to match the new spec.');
        } finally {
            file_put_contents($baselinePath, $originalBaseline);
            file_put_contents($changelogPath, $originalChangelog);
        }
    }
}
