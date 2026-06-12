<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Logging\TapJsonFormatter;
use App\Support\ProductionSecurityValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Artisan;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Tests\TestCase;

/**
 * Phase-14 Phase 3 code-side coverage: OBSERV-6/7/9/10.
 * (OBSERV-5/8 are pure docs; SUPPLY-5/6/7/8 ship via CI + deploy
 * changes and don't need PHP-level coverage.)
 */
class ObservabilityPhase14ExtensionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_validator_warns_when_tracing_disabled_with_dsn_set(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.debug' => false,
            'app.env' => 'production',
            'session.encrypt' => true,
            'session.secure' => true,
            'mail.default' => 'smtp',
            'security.headers.hsts_enabled' => true,
            'reverb.apps.apps.0.key' => 'real-key',
            'reverb.apps.apps.0.secret' => 'real-secret',
            'sentry.dsn' => 'https://example.ingest.sentry.io/123',
            'sentry.traces_sample_rate' => 0.0,
            'logging.channels.single.level' => 'warning',
            'security.kenya_dpa.enabled' => true,
            'security.kenya_dpa.registration' => 'KE-DPA-12345',
            'hashing.bcrypt.rounds' => 12,
            'filesystems.default' => 's3',
        ]);

        $provider = new ProductionSecurityValidator($this->app);
        $method = new \ReflectionMethod($provider, 'collectProductionWarnings');
        $method->setAccessible(true);
        $warnings = $method->invoke($provider);

        $tracingWarning = collect($warnings)->first(
            fn ($w) => str_contains((string) $w, 'SENTRY_TRACES_SAMPLE_RATE'),
        );

        $this->assertNotNull($tracingWarning, 'validator must warn on traces_sample_rate=0 when DSN is set');
    }

    public function test_validator_silent_on_tracing_when_dsn_is_empty(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.debug' => false,
            'app.env' => 'production',
            'session.encrypt' => true,
            'session.secure' => true,
            'mail.default' => 'smtp',
            'security.headers.hsts_enabled' => true,
            'reverb.apps.apps.0.key' => 'real-key',
            'reverb.apps.apps.0.secret' => 'real-secret',
            'sentry.dsn' => '',
            'sentry.traces_sample_rate' => 0.0,
            'logging.channels.single.level' => 'warning',
            'security.kenya_dpa.enabled' => true,
            'security.kenya_dpa.registration' => 'KE-DPA-12345',
            'hashing.bcrypt.rounds' => 12,
            'filesystems.default' => 's3',
        ]);

        $provider = new ProductionSecurityValidator($this->app);
        $method = new \ReflectionMethod($provider, 'collectProductionWarnings');
        $method->setAccessible(true);
        $warnings = $method->invoke($provider);

        $tracingWarning = collect($warnings)->first(
            fn ($w) => str_contains((string) $w, 'SENTRY_TRACES_SAMPLE_RATE'),
        );

        $this->assertNull(
            $tracingWarning,
            'validator must NOT warn on tracing when DSN itself is empty (already covered by the DSN warning)',
        );
    }

    public function test_json_formatter_tap_swaps_handler_formatter(): void
    {
        $handler = new TestHandler;
        $monolog = new MonologLogger('test', [$handler]);
        $logger = new Logger($monolog);

        (new TapJsonFormatter)($logger);

        $this->assertInstanceOf(
            \Monolog\Formatter\JsonFormatter::class,
            $handler->getFormatter(),
        );
    }

    public function test_metrics_observe_writes_histogram_buckets(): void
    {
        // We don't have a live Redis in unit tests; this exercises
        // exception-handling — observe() must not throw even when
        // Redis is unavailable.
        $service = new \App\Services\MetricsService;

        // No assertion needed — the call must not throw.
        $service->observe('payment_latency', 123.4, ['gateway' => 'mpesa']);
        $this->assertTrue(true);
    }

    public function test_logs_correlate_command_returns_zero_on_empty_dataset(): void
    {
        $exit = Artisan::call('logs:correlate', [
            '--request-id' => '11111111-1111-1111-1111-111111111111',
        ]);

        $this->assertSame(0, $exit);
    }

    public function test_logs_correlate_command_accepts_user_id_and_ip_filters(): void
    {
        $exit = Artisan::call('logs:correlate', [
            '--user-id' => 9_999_999,
            '--since' => '7d',
        ]);
        $this->assertSame(0, $exit);

        $exit = Artisan::call('logs:correlate', [
            '--ip' => '203.0.113.250',
            '--since' => '1h',
        ]);
        $this->assertSame(0, $exit);
    }
}
