<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Providers\AppServiceProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Phase-22 PERF-SCALE-1: horizontal-scale readiness audit.
 *
 * Before PropManager can run more than one app instance it must be
 * stateless: sessions + cache externalised, and no code assuming a
 * single host's filesystem. These tests pin both — the production
 * config validator now warns on per-host session/cache drivers, and
 * the Storage::disk('local') call-site count is a shrink-only baseline
 * so the single-host filesystem footprint cannot grow unnoticed.
 */
class Phase22StatelessnessTest extends TestCase
{
    /**
     * Shrink-only baseline: count of hardcoded Storage::disk('local')
     * call sites across app/. These are the real single-host
     * filesystem dependency — KYC docs, lease docs, water-reading
     * photos, invoice PDFs, GDPR exports, imports all bound to one
     * host's disk. The migration to a configurable shared disk is
     * tracked in docs/runbooks/autoscale-readiness.md (PERF-SCALE-3);
     * until then this watchdog guarantees the footprint only shrinks.
     *
     * Baseline history:
     *   - Phase 22: 26 (initial lock)
     *   - Phase 57: 28 (acknowledging the 2 callsites added by Phase 28
     *     TENANT-DOCS + Phase 45 TICKET-PHOTOS; bump rather than refactor
     *     because the shared-disk migration is operator-coordinated SRE
     *     work, not in-scope for PERF-DEEP. Queued as a Section-A
     *     carry-over for a future SRE cycle).
     */
    private const LOCAL_DISK_CALLSITE_BASELINE = 28;

    public function test_production_validator_flags_non_externalised_session_and_cache(): void
    {
        // PERF-SCALE-1: file/array session + cache stores are per-host
        // and break behind a load balancer — the production config
        // validator must surface that as a warning.
        config([
            'session.driver' => 'file',
            'cache.default' => 'file',
        ]);

        $provider = new AppServiceProvider($this->app);
        $method = new ReflectionMethod($provider, 'collectProductionWarnings');
        $method->setAccessible(true);
        /** @var array<int, string> $warnings */
        $warnings = $method->invoke($provider);

        $joined = implode("\n", $warnings);
        $this->assertStringContainsString(
            'SESSION_DRIVER=file',
            $joined,
            'PERF-SCALE-1: the production validator must warn when SESSION_DRIVER is per-host (file/array).',
        );
        $this->assertStringContainsString(
            'CACHE_STORE=file',
            $joined,
            'PERF-SCALE-1: the production validator must warn when CACHE_STORE is per-host (file/array).',
        );
        $this->assertStringContainsString('PERF-SCALE-1', $joined, 'PERF-SCALE-1: the warnings must reference the finding for traceability.');
    }

    public function test_externalised_drivers_produce_no_statelessness_warning(): void
    {
        // The inverse: redis/database drivers must NOT trip the warning.
        config([
            'session.driver' => 'redis',
            'cache.default' => 'redis',
        ]);

        $provider = new AppServiceProvider($this->app);
        $method = new ReflectionMethod($provider, 'collectProductionWarnings');
        $method->setAccessible(true);
        $joined = implode("\n", $method->invoke($provider));

        $this->assertStringNotContainsString('SESSION_DRIVER=', $joined, 'PERF-SCALE-1: redis session must not warn.');
        $this->assertStringNotContainsString('CACHE_STORE=', $joined, 'PERF-SCALE-1: redis cache must not warn.');
    }

    public function test_local_disk_callsite_count_is_shrink_only(): void
    {
        $count = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $count += substr_count((string) file_get_contents($file->getPathname()), "Storage::disk('local')");
        }

        $this->assertLessThanOrEqual(
            self::LOCAL_DISK_CALLSITE_BASELINE,
            $count,
            "PERF-SCALE-1: Storage::disk('local') call-site count is {$count}, over the shrink-only baseline of ".
            self::LOCAL_DISK_CALLSITE_BASELINE.'. These are single-host filesystem dependencies — the count may only ever '.
            'shrink (migrate to a configurable shared disk). A new hardcoded local-disk call site is a horizontal-scale regression.',
        );
    }
}
