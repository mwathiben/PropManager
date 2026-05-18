<?php

declare(strict_types=1);

namespace Tests\Feature\Cleanup;

use App\Services\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

/**
 * Phase-38 DEFER-CLEANUP-2 watchdog: consolidates the 5 stabilization
 * invariants from this audit cycle. Locked together in one class so
 * future cleanup cycles know exactly where the regression guards live.
 *
 * Invariants asserted:
 *   - DEFER-ROUTE-CONFLICT-2: route:cache compiles without LogicException
 *   - DEFER-METRICS-FALLBACK: MetricsService noops when Redis unavailable
 *   - DEFER-CASE-SENSITIVITY-2: no @/CapitalCase import paths
 *   - DEFER-TEST-HEALTH-2: total errors+failures stays at or below baseline
 *   - DEFER-BUILD-CI-3: bundle freshness within 24h of FE commits
 *
 * Per-invariant findings document the precise file + line evidence in
 * the PRD (phase-38-audit-prd.json).
 */
class Phase38CleanupSurfaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Phase-38 DEFER-ROUTE-CONFLICT-2: every route name must be unique
     * across the entire router. Duplicates break `php artisan route:cache`
     * with LogicException and disable production route caching. Phase 31
     * shipped a second `help.search` name; Phase 38 renamed it to
     * `help.api.search` to free the legacy public help portal's name.
     */
    public function test_route_cache_compiles_without_collision(): void
    {
        $routes = Route::getRoutes();
        $names = [];
        $duplicates = [];

        foreach ($routes->getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null || $name === '') {
                continue;
            }
            if (isset($names[$name])) {
                $duplicates[$name][] = $route->uri();
                if (! in_array($names[$name], $duplicates[$name], true)) {
                    array_unshift($duplicates[$name], $names[$name]);
                }

                continue;
            }
            $names[$name] = $route->uri();
        }

        $this->assertEmpty(
            $duplicates,
            'Duplicate route names detected (breaks route:cache):'."\n  - "
                .implode("\n  - ", array_map(
                    fn ($name, $uris) => "{$name} → ".implode(', ', $uris),
                    array_keys($duplicates),
                    array_values($duplicates),
                )),
        );
    }

    /**
     * Phase-38 DEFER-METRICS-FALLBACK-1: when no Redis client is
     * available (no phpredis extension AND no predis/predis), every
     * public MetricsService method must NOOP without throwing and
     * without logging on every call. The notice-once cache key
     * 'metrics:driver-unavailable-notice' is exempt — it logs at
     * most once per 24h.
     */
    public function test_metrics_service_noops_when_redis_unavailable(): void
    {
        MetricsService::resetRedisAvailabilityCache();
        // Force the unavailable branch — simulate "neither client
        // loaded" by stubbing the static through reflection.
        $reflection = new \ReflectionClass(MetricsService::class);
        $prop = $reflection->getProperty('redisAvailable');
        $prop->setAccessible(true);
        $prop->setValue(null, false);

        $metrics = new MetricsService();

        // Each method should return without throwing.
        $metrics->increment('test_counter');
        $metrics->observe('test_histogram', 42.5);
        $metrics->gauge('test_gauge', 7.0);
        $this->assertSame([], $metrics->snapshot());
        $this->assertSame([], $metrics->gaugeSnapshot());

        MetricsService::resetRedisAvailabilityCache();
    }

    /**
     * Phase-38 DEFER-METRICS-FALLBACK-2: when at least one Redis
     * client IS available (phpredis OR predis), redisAvailable()
     * returns true. With predis installed (Phase-38 dev dependency),
     * this should be true in every test/dev environment.
     */
    public function test_metrics_service_detects_predis_when_installed(): void
    {
        MetricsService::resetRedisAvailabilityCache();
        $this->assertTrue(
            MetricsService::redisAvailable(),
            'predis/predis must be installed via composer require predis/predis --dev.',
        );
        MetricsService::resetRedisAvailabilityCache();
    }

    /**
     * Phase-38 DEFER-CASE-SENSITIVITY-2: scripts/check-import-case.mjs
     * walks every @/... import and asserts the directory case matches
     * the filesystem. Windows is case-insensitive so wrong-case
     * imports build locally but break on Linux production. The script
     * runs as part of `npm run build` AND is invoked here so test
     * runs catch regressions even without a build.
     */
    public function test_no_capital_case_import_paths(): void
    {
        $projectRoot = base_path();
        $command = sprintf('node %s 2>&1', escapeshellarg($projectRoot.'/scripts/check-import-case.mjs'));
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $this->assertSame(
            0,
            $exitCode,
            "check-import-case.mjs reported mismatched directory case:\n".implode("\n", $output),
        );
    }

    /**
     * Phase-38 DEFER-BUILD-CI-3: the bundle freshness audit cron is
     * scheduled at the expected cadence + timezone. Stale bundles
     * silently break dashboards (yesterday's Phase-30 layout typo
     * froze the build for 36h), so the daily watchdog is critical.
     */
    public function test_bundle_freshness_audit_is_scheduled(): void
    {
        $events = collect(\Illuminate\Support\Facades\Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'bundle:freshness-audit'));

        $this->assertNotNull($entry, 'bundle:freshness-audit is not scheduled.');
        $this->assertSame('55 4 * * *', $entry->expression);
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    /**
     * Phase-38 DEFER-BUILD-CI-3: the alert registry contains the
     * stale_bundle_warning key so AlertFiringRecorder calls don't
     * surface "unknown alert key" warnings.
     */
    public function test_stale_bundle_warning_alert_registered(): void
    {
        $registry = collect(config('alerts.alerts'))->pluck('key')->all();
        $this->assertContains(
            'stale_bundle_warning',
            $registry,
            'stale_bundle_warning missing from config/alerts.php',
        );
    }

    /**
     * Phase-38 DEFER-TEST-HEALTH-2: ratchet on total error+failure
     * count across the full suite. Phase-38 scout measured 90 errors
     * + 9 failures = 99 total at the start of this cycle. The ratchet
     * caps future regressions at this baseline; every fix-the-test
     * PR lowers the constant. Once 0 is reached, the assertion stays
     * there forever as a regression guard.
     *
     * This test does NOT re-run the suite (would deadlock) — it reads
     * the last `php artisan test --log-junit storage/app/junit.xml`
     * artifact when present, and skips with a notice when not. CI
     * always emits the artifact before running this test class.
     */
    public function test_suite_error_count_at_or_below_baseline(): void
    {
        // Phase-53 TEST-DEBT-3: 99 → 98 after Phase29CiTest firstOrFail
        // pollution fix (TEST-DEBT-1). Shrink-only ratchet — never raise
        // this on legitimate test regression; raise only on
        // explicitly-accepted xfail.
        $baseline = 98;
        $junitPath = storage_path('app/junit.xml');

        if (! file_exists($junitPath)) {
            $this->markTestSkipped(
                'No junit.xml artifact found at storage/app/junit.xml. '
                .'CI emits this via `php artisan test --log-junit storage/app/junit.xml`. '
                .'Skipping ratchet check on local runs.',
            );
        }

        $xml = simplexml_load_file($junitPath);
        $errors = (int) ($xml['errors'] ?? 0);
        $failures = (int) ($xml['failures'] ?? 0);
        $actual = $errors + $failures;

        $this->assertLessThanOrEqual(
            $baseline,
            $actual,
            "Test suite has {$actual} errors+failures, baseline is {$baseline}. "
            ."New test failures violate the Phase-38 DEFER-TEST-HEALTH-2 ratchet. "
            .'Fix the failing tests OR raise the baseline (only on legitimate xfail).',
        );
    }

    /**
     * Phase-38 DEFER-CI-2: lang/{en,sw}/cleanup.php key parity per
     * Phase24CiTest convention. Operator-facing strings for metrics
     * driver fallback, bundle staleness, route-cache errors, and
     * test-health ratchet violations must mirror between locales.
     */
    public function test_cleanup_lang_namespace_has_parity(): void
    {
        $en = require lang_path('en/cleanup.php');
        $sw = require lang_path('sw/cleanup.php');

        $this->assertIsArray($en);
        $this->assertIsArray($sw);
        $this->assertSame(
            array_keys($this->flatten($en)),
            array_keys($this->flatten($sw)),
            'lang/{en,sw}/cleanup.php key order must match.',
        );
    }

    private function flatten(array $arr, string $prefix = ''): array
    {
        $out = [];
        foreach ($arr as $k => $v) {
            $key = $prefix === '' ? (string) $k : "{$prefix}.{$k}";
            if (is_array($v)) {
                $out += $this->flatten($v, $key);
            } else {
                $out[$key] = $v;
            }
        }

        return $out;
    }
}
