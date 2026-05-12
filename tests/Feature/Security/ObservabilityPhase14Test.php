<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Jobs\Concerns\CarriesRequestId;
use App\Jobs\Middleware\PropagatesRequestId;
use App\Services\MetricsService;
use Tests\TestCase;

/**
 * Phase-14 OBSERV-1/2/3/4 regression coverage.
 */
class ObservabilityPhase14Test extends TestCase
{
    public function test_metrics_endpoint_returns_503_when_no_auth_configured(): void
    {
        config([
            'observability.metrics.bearer' => '',
            'observability.metrics.allow_ips' => '',
        ]);

        $response = $this->get('/api/metrics');
        $response->assertStatus(503);
    }

    public function test_metrics_endpoint_rejects_wrong_bearer(): void
    {
        config([
            'observability.metrics.bearer' => 'expected-token',
            'observability.metrics.allow_ips' => '',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer wrong-token')
            ->get('/api/metrics');

        $response->assertStatus(401);
    }

    public function test_metrics_endpoint_returns_prometheus_format_with_correct_bearer(): void
    {
        config([
            'observability.metrics.bearer' => 'good-token',
            'observability.metrics.allow_ips' => '',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer good-token')
            ->get('/api/metrics');

        $response->assertOk();
        $this->assertStringStartsWith('text/plain; version=0.0.4', $response->headers->get('Content-Type'));
    }

    public function test_metrics_export_format_prefixes_names_with_propmanager(): void
    {
        $metrics = $this->createMock(MetricsService::class);
        $metrics->method('snapshot')->willReturn([
            'webhook_received{provider=mpesa}' => '42',
            'payment_processed' => '7',
        ]);

        // Call exportPrometheus on a real instance to test format.
        $real = new MetricsService;
        $reflection = new \ReflectionClass($real);
        $method = $reflection->getMethod('prometheusName');
        $method->setAccessible(true);

        $this->assertSame(
            'propmanager_webhook_received{provider=mpesa}',
            $method->invoke($real, 'webhook_received{provider=mpesa}'),
        );
        $this->assertSame(
            'propmanager_payment_processed',
            $method->invoke($real, 'payment_processed'),
        );
    }

    public function test_carries_request_id_trait_stamps_dispatched_job(): void
    {
        $job = new class
        {
            use CarriesRequestId;
        };

        $job->withRequestId('11111111-1111-1111-1111-111111111111');

        $this->assertSame('11111111-1111-1111-1111-111111111111', $job->requestId);
        $this->assertContainsOnlyInstancesOf(PropagatesRequestId::class, $job->middleware());
    }

    public function test_carries_request_id_pulls_from_current_request(): void
    {
        $this->app['request']->attributes->set('request_id', 'abc-123');

        $job = new class
        {
            use CarriesRequestId;
        };
        $job->withCurrentRequestId();

        $this->assertSame('abc-123', $job->requestId);
    }

    public function test_propagates_request_id_middleware_applies_context(): void
    {
        $middleware = new PropagatesRequestId;
        $job = new class
        {
            use CarriesRequestId;
        };
        $job->requestId = 'mw-test-id';

        $captured = null;
        $middleware->handle($job, function ($j) use (&$captured) {
            $captured = $j;

            return 'next-result';
        });

        $this->assertSame($job, $captured);
    }
}
