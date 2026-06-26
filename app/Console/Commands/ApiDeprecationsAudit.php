<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Phase-25 API-VERSION-3: keep docs/api/deprecations.md honest.
 *
 * When an engineer flags a route with the deprecated:YYYY-MM-DD
 * middleware (API-VERSION-2), the consumer-facing changelog at
 * docs/api/deprecations.md must list it — otherwise consumers learn
 * about the sunset only when their 410 Gone fires.
 *
 * This command:
 *   - scans every API route for the `deprecated:` middleware
 *   - asserts each deprecated route is covered in deprecations.md
 *   - with --fix appends a skeleton block for any undocumented route
 *     so the operator only has to fill in `reason` + `replacement`
 *
 * The exit code is the CI gate: non-zero if any deprecated route is
 * undocumented. This pairs with VERSION-1 (the operator runbook),
 * VERSION-2 (the header middleware), and DOC-3 (the spec-diff
 * changelog) to lock the per-release deprecation contract.
 */
class ApiDeprecationsAudit extends Command
{
    protected $signature = 'api:deprecations:audit
        {--fix : Append a skeleton entry for any undocumented deprecated route}';

    protected $description = 'Phase-25 API-VERSION-3: verify every route marked deprecated:YYYY-MM-DD is documented in docs/api/deprecations.md.';

    public function handle(): int
    {
        $docPath = base_path('docs/api/deprecations.md');
        if (! file_exists($docPath)) {
            $this->error("Missing {$docPath}. Phase-25 API-VERSION-1 must ship before this audit.");

            return self::FAILURE;
        }

        $deprecated = $this->collectDeprecatedRoutes();
        $doc = (string) file_get_contents($docPath);

        if ($deprecated === []) {
            $this->info('No routes carry the deprecated middleware. Nothing to audit.');

            return self::SUCCESS;
        }

        $missing = $this->findMissingEntries($deprecated, $doc);

        $this->line(sprintf('Found %d deprecated route(s). Documented: %d. Missing: %d.',
            count($deprecated),
            count($deprecated) - count($missing),
            count($missing),
        ));

        if ($missing === []) {
            return self::SUCCESS;
        }

        return $this->reportMissingAndMaybeFix($docPath, $doc, $missing);
    }

    /**
     * @param  list<array{route_signature: string, sunset_at: string}>  $deprecated
     * @return list<array{route_signature: string, sunset_at: string}>
     */
    private function findMissingEntries(array $deprecated, string $doc): array
    {
        $missing = [];
        foreach ($deprecated as $entry) {
            if (! str_contains($doc, $entry['route_signature'])) {
                $missing[] = $entry;
            }
        }

        return $missing;
    }

    /**
     * @param  list<array{route_signature: string, sunset_at: string}>  $missing
     */
    private function reportMissingAndMaybeFix(string $docPath, string $doc, array $missing): int
    {
        foreach ($missing as $entry) {
            $this->warn("  undocumented: {$entry['route_signature']} (sunset {$entry['sunset_at']})");
        }

        if (! $this->option('fix')) {
            $this->error('Run with --fix to append skeleton entries, then commit docs/api/deprecations.md alongside the middleware change.');

            return self::FAILURE;
        }

        $appended = $this->appendSkeletons($docPath, $doc, $missing);
        $this->info("Appended {$appended} skeleton entries to {$docPath}. Fill in `replacement` + `reason` before committing.");

        return self::SUCCESS;
    }

    /**
     * @return list<array{route_signature: string, sunset_at: string}>
     */
    private function collectDeprecatedRoutes(): array
    {
        $out = [];
        /** @var Route $route */
        foreach (RouteFacade::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'deprecated:')) {
                    continue;
                }
                $sunset = substr($middleware, strlen('deprecated:'));
                $method = $route->methods()[0] ?? 'GET';
                $uri = '/'.ltrim($route->uri(), '/');
                $out[] = [
                    'route_signature' => "{$method} {$uri}",
                    'sunset_at' => $sunset,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  list<array{route_signature: string, sunset_at: string}>  $missing
     */
    private function appendSkeletons(string $path, string $existing, array $missing): int
    {
        $today = now()->format('Y-m-d');
        $blocks = [];
        foreach ($missing as $entry) {
            $blocks[] = <<<MD
### {$entry['route_signature']}

- **deprecated_at**: {$today}
- **sunset_at**: {$entry['sunset_at']}
- **replacement**: _TODO_
- **upgrade_doc**: _TODO_
- **reason**: _TODO — operator must fill before merging._

MD;
        }

        $updated = rtrim($existing)."\n\n".implode("\n", $blocks);
        file_put_contents($path, $updated);

        return count($blocks);
    }
}
