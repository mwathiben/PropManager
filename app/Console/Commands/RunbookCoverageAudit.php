<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MetricsService;
use App\Services\Sre\AlertRegistry;
use Illuminate\Console\Command;

/**
 * Phase-32 SRE-RUNBOOK-2: verify every alert in the registry points
 * at an existing runbook file AND, if a heading anchor is included,
 * that the anchor matches a markdown heading. A broken runbook link
 * is silently useless until an on-call person hits it at 3am.
 *
 * Anchor matching: github-flavored markdown lowercases + replaces
 * non-alphanumerics with hyphens. We mirror that transformation.
 *
 * Emits:
 *   - runbook_coverage_broken_links_count gauge
 *   - non-zero exit code in --fail-on-broken mode for CI
 */
class RunbookCoverageAudit extends Command
{
    protected $signature = 'runbook:coverage-audit {--fail-on-broken : exit non-zero if any link is broken}';

    protected $description = 'Phase-32 SRE-RUNBOOK-2: validate every alert.runbook reference.';

    public function handle(AlertRegistry $registry, MetricsService $metrics): int
    {
        $broken = [];
        foreach ($registry->all() as $alert) {
            $ref = (string) ($alert['runbook'] ?? '');
            if ($ref === '') {
                $broken[] = ['alert' => $alert['key'], 'reason' => 'empty runbook ref'];

                continue;
            }
            [$path, $anchor] = $this->splitRef($ref);
            $abs = base_path($path);
            if (! file_exists($abs)) {
                $broken[] = ['alert' => $alert['key'], 'reason' => "file not found: {$path}"];

                continue;
            }
            if ($anchor !== null && ! $this->anchorExists($abs, $anchor)) {
                $broken[] = ['alert' => $alert['key'], 'reason' => "anchor #{$anchor} not in {$path}"];
            }
        }

        $metrics->gauge('runbook_coverage_broken_links_count', (float) count($broken));

        if ($broken !== []) {
            $this->error('Broken runbook references:');
            foreach ($broken as $row) {
                $this->line("  - {$row['alert']}: {$row['reason']}");
            }
            if ($this->option('fail-on-broken')) {
                return self::FAILURE;
            }
        } else {
            $this->info('All '.count($registry->all()).' alert runbook references resolve.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function splitRef(string $ref): array
    {
        $hashPos = strpos($ref, '#');
        if ($hashPos === false) {
            return [$ref, null];
        }

        return [substr($ref, 0, $hashPos), substr($ref, $hashPos + 1)];
    }

    private function anchorExists(string $absPath, string $anchor): bool
    {
        $contents = (string) file_get_contents($absPath);
        if ($contents === '') {
            return false;
        }
        if (! preg_match_all('/^#{1,6}\s+(.+)$/m', $contents, $m)) {
            return false;
        }
        foreach ($m[1] as $heading) {
            if ($this->slugify($heading) === $anchor) {
                return true;
            }
        }

        return false;
    }

    private function slugify(string $heading): string
    {
        $lower = mb_strtolower(trim($heading));
        $hyphens = preg_replace('/[^a-z0-9]+/', '-', $lower) ?? '';

        return trim($hyphens, '-');
    }
}
