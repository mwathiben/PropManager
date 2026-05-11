<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Exceptions\DomainException;
use Sentry\State\HubInterface;
use Tests\TestCase;

/**
 * OBS-1: Sentry SDK must be installed AND DomainException::report()
 * must forward to Sentry::captureException so ops can aggregate
 * errors across replicas. With no DSN configured in CI, the SDK is a
 * silent no-op — these tests assert wiring without requiring a
 * live Sentry project.
 */
class SentryIntegrationTest extends TestCase
{
    public function test_sentry_hub_is_bound_in_the_container(): void
    {
        $this->assertTrue($this->app->bound(HubInterface::class));
        $this->assertInstanceOf(HubInterface::class, $this->app->make(HubInterface::class));
    }

    public function test_sentry_capture_helper_is_loaded(): void
    {
        $this->assertTrue(function_exists('Sentry\captureException'));
    }

    public function test_domain_exception_report_runs_without_error_when_dsn_is_empty(): void
    {
        // DSN is intentionally blank in CI. report() should still
        // log + invoke Sentry capture (which silently no-ops).
        $exception = new class('boom', 'TEST_OBS1') extends DomainException {};

        // Should not throw.
        $exception->report();

        $this->assertTrue(true);
    }
}
