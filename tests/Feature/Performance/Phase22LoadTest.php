<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use Tests\TestCase;

/**
 * Phase-22 PERF-LOAD-1: k6 load-test harness watchdog.
 *
 * k6 is a separate binary that is not on the PATH in PHPUnit runs, so
 * these tests verify the scripts EXIST and expose the structure k6
 * needs (an `options` export, a default/scenario exec function). The
 * actual `k6 inspect` syntax check runs in the CI load-smoke job
 * (PERF-LOAD-2). If k6 happens to be installed locally, the harness
 * test opportunistically runs `k6 inspect` too.
 */
class Phase22LoadTest extends TestCase
{
    private function loadPath(string $relative): string
    {
        return base_path('tests/load/'.$relative);
    }

    public function test_k6_harness_files_exist(): void
    {
        $expected = [
            'smoke.js',
            'baseline.js',
            'lib/config.js',
            'lib/auth.js',
            'README.md',
        ];

        foreach ($expected as $file) {
            $this->assertFileExists(
                $this->loadPath($file),
                "PERF-LOAD-1: tests/load/{$file} must exist.",
            );
        }
    }

    public function test_smoke_script_exports_options_and_default(): void
    {
        $smoke = file_get_contents($this->loadPath('smoke.js'));

        $this->assertStringContainsString(
            'export const options',
            $smoke,
            'PERF-LOAD-1: smoke.js must export an `options` object for k6.',
        );
        $this->assertStringContainsString(
            'export default function',
            $smoke,
            'PERF-LOAD-1: smoke.js must export a default VU function.',
        );
        $this->assertStringContainsString(
            'SMOKE_THRESHOLDS',
            $smoke,
            'PERF-LOAD-1: smoke.js must apply SMOKE_THRESHOLDS so a latency breach fails the CI gate.',
        );
    }

    public function test_baseline_script_exports_scenarios(): void
    {
        $baseline = file_get_contents($this->loadPath('baseline.js'));

        $this->assertStringContainsString(
            'export const options',
            $baseline,
            'PERF-LOAD-1: baseline.js must export an `options` object.',
        );
        $this->assertStringContainsString(
            'scenarios',
            $baseline,
            'PERF-LOAD-1: baseline.js must define staged scenarios (read paths + webhook ingress).',
        );
        // The webhook scenario must use an invalid signature — it is the
        // data-safety contract for the write-path measurement.
        $this->assertStringContainsString(
            'invalid-signature',
            $baseline,
            'PERF-LOAD-1: the webhook ingress scenario must use an invalid signature (data-safe — never reaches a handler).',
        );
    }

    public function test_config_centralises_env_overridable_knobs(): void
    {
        $config = file_get_contents($this->loadPath('lib/config.js'));

        $this->assertStringContainsString('__ENV.BASE_URL', $config, 'PERF-LOAD-1: BASE_URL must be env-overridable.');
        $this->assertStringContainsString('LOAD_USER', $config, 'PERF-LOAD-1: config must define the dedicated load-test user.');
        $this->assertStringContainsString('SMOKE_THRESHOLDS', $config, 'PERF-LOAD-1: config must define SMOKE_THRESHOLDS.');
        $this->assertStringContainsString('BASELINE_THRESHOLDS', $config, 'PERF-LOAD-1: config must define BASELINE_THRESHOLDS.');
    }

    public function test_k6_inspect_passes_when_k6_is_available(): void
    {
        $k6 = trim((string) shell_exec('command -v k6 2>/dev/null'));
        if ($k6 === '') {
            $this->markTestSkipped('k6 is not installed — syntax check runs in the CI load-smoke job instead.');
        }

        foreach (['smoke.js', 'baseline.js'] as $script) {
            $output = [];
            $exit = 0;
            exec(escapeshellarg($k6).' inspect '.escapeshellarg($this->loadPath($script)).' 2>&1', $output, $exit);
            $this->assertSame(0, $exit, "PERF-LOAD-1: `k6 inspect {$script}` must exit 0. Output: ".implode("\n", $output));
        }
    }
}
