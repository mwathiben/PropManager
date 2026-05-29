<?php

declare(strict_types=1);

namespace Tests\Feature;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Phase-79 NAV-REACH-1: a page can render yet be unreachable because nothing
 * links to it (the 2026-05-21 "can't navigate to the new hubs" report). This
 * guard asserts the user-facing hubs/pages added in recent phases are each
 * referenced by a route('<name>') link somewhere under resources/js — so a
 * future change that drops the link fails here, not in the user's hands.
 */
class Phase79NavReachabilityTest extends TestCase
{
    /**
     * @return array<int, array{0:string}>
     */
    public static function linkedRoutes(): array
    {
        return [
            // Phase-79 water hub + tenant water.
            ['water.hub'],
            ['tenant.water'],
            // Phase-76/77 orphans wired this phase.
            ['tenant.wallet.index'],
            ['ops.onboarding.funnel'],
            // Phase-78 property tier.
            ['properties.index'],
            ['properties.benchmark'],
            // Phase-75 maintenance depth pages.
            ['maintenance.vendor-performance'],
            ['parts.pricing'],
            ['maintenance.photos'],
        ];
    }

    #[DataProvider('linkedRoutes')]
    public function test_route_is_linked_in_the_frontend(string $name): void
    {
        $this->assertTrue(
            $this->routeReferences()->contains($name),
            "Route '{$name}' is not referenced by any route('{$name}') link under resources/js — it would be unreachable from the UI.",
        );
    }

    public function test_nav_audit_baseline_is_present(): void
    {
        $this->assertFileExists(base_path('scripts/nav-audit.mjs'));
        $this->assertFileExists(base_path('scripts/nav-audit-baseline.json'));
    }

    private function routeReferences(): \Illuminate\Support\Collection
    {
        static $refs = null;
        if ($refs !== null) {
            return $refs;
        }

        $names = [];
        $dir = resource_path('js');
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($it as $file) {
            if (! in_array($file->getExtension(), ['vue', 'ts', 'js'], true)) {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            if (preg_match_all('/route\(\s*[\'"]([A-Za-z0-9_.-]+)[\'"]/', $src, $m) > 0) {
                foreach ($m[1] as $n) {
                    $names[$n] = true;
                }
            }
        }

        return $refs = collect(array_keys($names));
    }
}
