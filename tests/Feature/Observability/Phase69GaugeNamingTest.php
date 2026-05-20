<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use Tests\TestCase;

/**
 * Phase-69 GAUGE-NAMING-2: executable guard for the metric-naming
 * convention (docs/runbooks/metrics-naming.md). Every gauge an alert
 * reads must be snake_case AND carry a recognized unit/aggregate/window
 * token, so a future alert cannot register a malformed or token-less
 * gauge name.
 */
class Phase69GaugeNamingTest extends TestCase
{
    /** Recognized unit / aggregate tokens (matched per `_`-segment). */
    private const TOKENS = [
        'count', 'total', 'rate', 'ratio', 'score', 'depth', 'bytes',
        'hours', 'minutes', 'seconds', 'ms', 'usd', 'days', 'age', 'drift',
        'burn', 'percent',
    ];

    /**
     * Boolean-liveness gauges (the `_up` idiom): allowlisted exactly rather
     * than via a generic `up` token, which would false-accept names like
     * `gateway_warm_up` that carry no measurement semantics.
     */
    private const LIVENESS_GAUGES = ['dependency_up'];

    /** Names predating the convention that don't fit cleanly (none today). */
    private const GRANDFATHERED = [];

    public function test_every_alert_gauge_follows_the_naming_convention(): void
    {
        $alerts = config('alerts.alerts');
        $this->assertIsArray($alerts);
        $this->assertNotEmpty($alerts);

        $badFormat = [];
        $noToken = [];

        foreach ($alerts as $alert) {
            $gauge = $alert['gauge'] ?? null;
            $this->assertIsString($gauge, 'Every alert must declare a string gauge: '.json_encode($alert));

            if (in_array($gauge, self::GRANDFATHERED, true) || in_array($gauge, self::LIVENESS_GAUGES, true)) {
                continue;
            }

            if (preg_match('/^[a-z][a-z0-9_]*$/', $gauge) !== 1) {
                $badFormat[] = $gauge;

                continue;
            }

            if (! $this->hasRecognizedToken($gauge)) {
                $noToken[] = $gauge;
            }
        }

        $this->assertSame([], $badFormat, 'Gauges must be snake_case lowercase (see docs/runbooks/metrics-naming.md): '.implode(', ', $badFormat));
        $this->assertSame([], $noToken, 'Gauges must carry a unit/aggregate token (count, rate, ratio, hours, ...): '.implode(', ', $noToken));
    }

    private function hasRecognizedToken(string $gauge): bool
    {
        // A window (_24h, _15m, _7d, _90d) or percentile (_p90, _p999)
        // suffix counts as a recognized measurement qualifier — matched by
        // shape so any sane window/percentile passes without enumerating it.
        if (preg_match('/_(p\d{2,3}|\d+[smhdwy])$/', $gauge) === 1) {
            return true;
        }

        $parts = explode('_', $gauge);

        foreach (self::TOKENS as $token) {
            if (in_array($token, $parts, true)) {
                return true;
            }
        }

        return false;
    }
}
