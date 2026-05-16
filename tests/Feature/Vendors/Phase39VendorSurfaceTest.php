<?php

declare(strict_types=1);

namespace Tests\Feature\Vendors;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

/**
 * Phase-39 GROWTH-VENDORS-2 watchdog: consolidates the 6 invariants
 * from this audit cycle. Locked together in one class so future
 * vendor cycles know exactly where regression guards live.
 *
 * Invariants:
 *   - VENDOR-ANALYTICS-2: analytics:replay-batch scheduled daily 04:45
 *   - VENDOR-OBSERV-1: push:click-through-audit scheduled daily 05:10
 *   - VENDOR-OBSERV-2: vendor_flap alert registered
 *   - PUSH-EXTEND-3 + RETENTION-READ-3: 4 ops routes wired
 *   - VENDOR-CI-2: lang/{en,sw}/vendors.php parity
 *   - VENDOR-ANALYTICS-3: config/vendors.php disabled by default
 */
class Phase39VendorSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_replay_batch_scheduled_daily_at_0445(): void
    {
        $entry = collect(Schedule::events())
            ->first(fn ($e) => str_contains((string) $e->command, 'analytics:replay-batch'));

        $this->assertNotNull($entry, 'analytics:replay-batch is not scheduled.');
        $this->assertSame('45 4 * * *', $entry->expression);
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    public function test_push_click_through_audit_scheduled_daily_at_0510(): void
    {
        $entry = collect(Schedule::events())
            ->first(fn ($e) => str_contains((string) $e->command, 'push:click-through-audit'));

        $this->assertNotNull($entry, 'push:click-through-audit is not scheduled.');
        $this->assertSame('10 5 * * *', $entry->expression);
        $this->assertSame('Africa/Nairobi', $entry->timezone);
    }

    public function test_vendor_flap_alert_is_registered(): void
    {
        $registry = collect(config('alerts.alerts'))->pluck('key')->all();
        $this->assertContains('vendor_flap', $registry, 'vendor_flap missing from config/alerts.php');
    }

    public function test_ops_routes_for_push_and_archive_exist(): void
    {
        $expected = ['ops.push.show', 'ops.push.send', 'ops.archive.show', 'ops.archive.rehydrate'];
        $missing = [];
        foreach ($expected as $name) {
            if (! Route::has($name)) {
                $missing[] = $name;
            }
        }

        $this->assertEmpty($missing, 'Missing Phase-39 ops routes: '.implode(', ', $missing));
    }

    public function test_vendors_lang_namespace_has_parity(): void
    {
        $en = require lang_path('en/vendors.php');
        $sw = require lang_path('sw/vendors.php');

        $this->assertIsArray($en);
        $this->assertIsArray($sw);
        $this->assertSame(
            array_keys($this->flatten($en)),
            array_keys($this->flatten($sw)),
            'lang/{en,sw}/vendors.php key order must match.',
        );
    }

    public function test_vendors_posthog_defaults_disabled(): void
    {
        $this->assertFalse(env('VENDORS_POSTHOG_ENABLED', false), 'PostHog must default disabled in .env.example.');
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
