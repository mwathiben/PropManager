<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Dedoc\Scramble\Generator;
use Illuminate\Console\Command;

/**
 * Phase-25 API-DOC-3: produce a human-readable changelog block by
 * diffing the current OpenAPI spec against a checked-in baseline.
 *
 * Flow per release:
 *   1. Engineer runs `php artisan api:changelog` to preview the diff.
 *   2. If satisfied, runs `php artisan api:changelog --commit` which
 *      appends a dated block to docs/api/changelog.md AND rotates the
 *      baseline at docs/api/openapi-baseline.json. Both files go into
 *      the same commit.
 *
 * Why not auto-generate everything in CI? Because the WRITE that
 * matters (the prose `reason:` line behind each change) is a
 * human-judgment artifact. The tool surfaces the structural delta;
 * the operator authors the rationale.
 *
 * Paired with API-CI-1 (the drift watchdog) and API-VERSION-2 (the
 * deprecation header middleware) this completes the per-release
 * triangle: ship without forgetting either the consumer-facing prose
 * or the machine-readable signal.
 */
class ApiChangelog extends Command
{
    protected $signature = 'api:changelog
        {--commit : Rotate the baseline + append a block to docs/api/changelog.md (otherwise just print the diff)}
        {--date= : Override the date stamp (defaults to today). Useful for replays.}';

    protected $description = 'Phase-25 API-DOC-3: diff the live OpenAPI spec against the checked-in baseline and emit a consumer-facing changelog block.';

    public function handle(): int
    {
        $baselinePath = base_path('docs/api/openapi-baseline.json');
        $changelogPath = base_path('docs/api/changelog.md');

        $generator = app(Generator::class);
        $current = $generator();
        $baseline = file_exists($baselinePath)
            ? (array) json_decode((string) file_get_contents($baselinePath), true)
            : ['paths' => []];

        $diff = $this->computeDiff(
            (array) ($baseline['paths'] ?? []),
            (array) ($current['paths'] ?? []),
        );

        if ($diff['empty']) {
            $this->info('No spec changes since baseline. Nothing to record.');

            return self::SUCCESS;
        }

        $date = (string) ($this->option('date') ?: now()->format('Y-m-d'));
        $block = $this->renderBlock($date, $diff);

        $this->line($block);

        if (! $this->option('commit')) {
            $this->info('Run with --commit to rotate the baseline + append the block to docs/api/changelog.md.');

            return self::SUCCESS;
        }

        $this->appendBlock($changelogPath, $block);
        file_put_contents(
            $baselinePath,
            json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );

        $this->info("Wrote {$changelogPath} and rotated {$baselinePath}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $oldPaths
     * @param  array<string, mixed>  $newPaths
     * @return array{added: list<string>, removed: list<string>, changed: list<string>, empty: bool}
     */
    private function computeDiff(array $oldPaths, array $newPaths): array
    {
        $added = array_values(array_diff(array_keys($newPaths), array_keys($oldPaths)));
        $removed = array_values(array_diff(array_keys($oldPaths), array_keys($newPaths)));

        sort($added);
        sort($removed);

        $changed = [];
        foreach ($newPaths as $path => $definition) {
            if (! array_key_exists($path, $oldPaths)) {
                continue;
            }
            if (json_encode($definition) !== json_encode($oldPaths[$path])) {
                $changed[] = $path;
            }
        }
        sort($changed);

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
            'empty' => $added === [] && $removed === [] && $changed === [],
        ];
    }

    /**
     * @param  array{added: list<string>, removed: list<string>, changed: list<string>, empty: bool}  $diff
     */
    private function renderBlock(string $date, array $diff): string
    {
        $lines = ["## {$date}", ''];

        if ($diff['added'] !== []) {
            $lines[] = '### Added';
            $lines[] = '';
            foreach ($diff['added'] as $path) {
                $lines[] = "- `{$path}`";
            }
            $lines[] = '';
        }

        if ($diff['removed'] !== []) {
            $lines[] = '### Removed';
            $lines[] = '';
            foreach ($diff['removed'] as $path) {
                $lines[] = "- `{$path}` — breaking. Consumers must migrate before the next release.";
            }
            $lines[] = '';
        }

        if ($diff['changed'] !== []) {
            $lines[] = '### Changed';
            $lines[] = '';
            foreach ($diff['changed'] as $path) {
                $lines[] = "- `{$path}` — review request/response schema for breaking shape changes.";
            }
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines))."\n";
    }

    private function appendBlock(string $path, string $block): void
    {
        $existing = file_exists($path) ? (string) file_get_contents($path) : "# API changelog\n\n";
        $marker = "<!-- changelog:entries -->\n";

        if (str_contains($existing, $marker)) {
            $updated = str_replace($marker, $marker."\n".$block, $existing);
        } else {
            $updated = rtrim($existing)."\n\n".$marker."\n".$block;
        }

        file_put_contents($path, $updated);
    }
}
