<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Http\Middleware\RecordRequestLatency;
use App\Services\MetricsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Phase-22 PERF-SLO-1: per-request latency middleware watchdog.
 *
 * CI has no live Redis (CACHE_STORE=array), so these tests drive the
 * middleware against a mocked MetricsService rather than asserting
 * Redis histogram state — the contract under test is "terminate()
 * emits http_request_ms with bounded labels and never throws".
 */
class Phase22SloTest extends TestCase
{
    private function namedRouteRequest(string $name = 'invoices.index', string $method = 'GET'): Request
    {
        $request = Request::create('/invoices', $method);
        $route = (new Route([$method], '/invoices', []))->name($name);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    public function test_latency_middleware_emits_http_request_ms(): void
    {
        $captured = [];
        $metrics = $this->createMock(MetricsService::class);
        $metrics->expects($this->once())
            ->method('observe')
            ->willReturnCallback(function (string $name, float $value, array $labels) use (&$captured): void {
                $captured = compact('name', 'value', 'labels');
            });

        $middleware = new RecordRequestLatency($metrics);
        $request = $this->namedRouteRequest();
        $response = new Response('ok', 200);

        $middleware->handle($request, fn () => $response);
        $middleware->terminate($request, $response);

        $this->assertSame('http_request_ms', $captured['name'], 'PERF-SLO-1: the histogram metric must be named http_request_ms.');
        $this->assertGreaterThanOrEqual(0.0, $captured['value'], 'PERF-SLO-1: elapsed time must be a non-negative millisecond value.');
    }

    public function test_latency_label_cardinality_is_bounded(): void
    {
        $captured = [];
        $metrics = $this->createMock(MetricsService::class);
        $metrics->method('observe')
            ->willReturnCallback(function (string $name, float $value, array $labels) use (&$captured): void {
                $captured = $labels;
            });

        $middleware = new RecordRequestLatency($metrics);
        $request = $this->namedRouteRequest('invoices.index', 'GET');
        $response = new Response('not found', 404);

        $middleware->handle($request, fn () => $response);
        $middleware->terminate($request, $response);

        $this->assertSame('invoices.index', $captured['route'], 'PERF-SLO-1: route label must be the route NAME, not the URI.');
        $this->assertSame('GET', $captured['method'], 'PERF-SLO-1: method label is the HTTP verb.');
        $this->assertMatchesRegularExpression(
            '/^[1-5]xx$/',
            $captured['status'],
            'PERF-SLO-1: status label must be bucketed to a class (e.g. 4xx), never the raw code — cardinality control.',
        );
        $this->assertSame('4xx', $captured['status'], 'PERF-SLO-1: a 404 must bucket to 4xx.');
    }

    public function test_unmatched_route_falls_back_to_unmatched_label(): void
    {
        $captured = [];
        $metrics = $this->createMock(MetricsService::class);
        $metrics->method('observe')
            ->willReturnCallback(function (string $name, float $value, array $labels) use (&$captured): void {
                $captured = $labels;
            });

        $middleware = new RecordRequestLatency($metrics);
        $request = Request::create('/no-such-route', 'GET'); // no route resolver
        $response = new Response('not found', 404);

        $middleware->handle($request, fn () => $response);
        $middleware->terminate($request, $response);

        $this->assertSame('unmatched', $captured['route'], 'PERF-SLO-1: an unnamed/unmatched route must fall back to the "unmatched" label.');
    }

    public function test_metric_write_failure_does_not_throw(): void
    {
        // PERF-SLO-1 fail-open posture: a Redis/metrics hiccup must
        // never turn into a 500. terminate() swallows the throw.
        $metrics = $this->createMock(MetricsService::class);
        $metrics->method('observe')->willThrowException(new \RuntimeException('redis down'));

        $middleware = new RecordRequestLatency($metrics);
        $request = $this->namedRouteRequest();
        $response = new Response('ok', 200);

        $middleware->handle($request, fn () => $response);
        $middleware->terminate($request, $response);

        $this->expectNotToPerformAssertions();
    }

    public function test_latency_middleware_is_registered_on_web_and_api(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringContainsString(
            'RecordRequestLatency::class',
            $bootstrap,
            'PERF-SLO-1: RecordRequestLatency must be registered in bootstrap/app.php.',
        );
        // Must appear for both the web and api groups — assert it shows
        // up at least twice (web append + api append).
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($bootstrap, 'RecordRequestLatency::class'),
            'PERF-SLO-1: RecordRequestLatency must be appended to BOTH the web and api middleware groups.',
        );
    }

    public function test_slo_budgets_are_configured(): void
    {
        // PERF-SLO-2: SLO budgets must live in config as a machine-
        // readable definition, not just in prose in slo.md.
        $budgets = config('observability.slo.latency_budgets_ms');

        $this->assertIsArray($budgets, 'PERF-SLO-2: observability.slo.latency_budgets_ms must be configured.');
        foreach (['read_path', 'write_path', 'webhook', 'report'] as $class) {
            $this->assertArrayHasKey($class, $budgets, "PERF-SLO-2: a p95 budget must be defined for the '{$class}' route class.");
            $this->assertGreaterThan(0, $budgets[$class], "PERF-SLO-2: the '{$class}' budget must be a positive millisecond value.");
        }

        $this->assertIsFloat(config('observability.slo.error_rate_budget'), 'PERF-SLO-2: a global error-rate budget must be configured.');
        $this->assertGreaterThan(0, config('observability.slo.evaluation_window_days'), 'PERF-SLO-2: an evaluation window must be configured.');
    }

    public function test_route_class_resolver_buckets_known_routes(): void
    {
        // PERF-SLO-2: the resolver is the single shared definition of
        // what each route class means.
        $this->assertSame('webhook', \App\Support\RouteClassResolver::classify('webhooks.paystack', 'POST'));
        $this->assertSame('report', \App\Support\RouteClassResolver::classify('reports.arrears', 'GET'));
        $this->assertSame('write_path', \App\Support\RouteClassResolver::classify('invoices.store', 'POST'));
        $this->assertSame('write_path', \App\Support\RouteClassResolver::classify('invoices.update', 'PUT'));
        $this->assertSame('read_path', \App\Support\RouteClassResolver::classify('invoices.index', 'GET'));
        // Method fallback: a POST to a non-.store route is still a write.
        $this->assertSame('write_path', \App\Support\RouteClassResolver::classify('payments.record', 'POST'));
        // Unmatched routes default to read_path.
        $this->assertSame('read_path', \App\Support\RouteClassResolver::classify(null, 'GET'));
    }

    public function test_slo_report_is_registered(): void
    {
        $this->assertArrayHasKey(
            'slo:report',
            \Illuminate\Support\Facades\Artisan::all(),
            'PERF-SLO-3: slo:report must be a registered artisan command.',
        );
    }

    public function test_slo_report_handles_no_samples_gracefully(): void
    {
        // No live Redis in CI -> snapshot() is empty -> the command must
        // exit SUCCESS with an informative message, not crash.
        $exit = \Illuminate\Support\Facades\Artisan::call('slo:report');
        $this->assertSame(0, $exit, 'PERF-SLO-3: slo:report with no samples must exit SUCCESS.');
    }

    public function test_slo_report_flags_out_of_budget_route_class(): void
    {
        // PERF-SLO-3: seed a histogram where write_path p95 is well
        // above its 1000ms budget; --fail-on-breach must exit non-zero.
        $today = now()->format('Y-m-d');
        $histogram = [
            // cumulative buckets: nothing <= 1000ms, everything in the
            // 1000-2500ms band -> p95 interpolates to ~2425ms.
            'http_request_ms_bucket{le=1000,method=POST,route=invoices.store,status=2xx}' => 0,
            'http_request_ms_bucket{le=2500,method=POST,route=invoices.store,status=2xx}' => 100,
            'http_request_ms_bucket{le=+Inf,method=POST,route=invoices.store,status=2xx}' => 100,
            'http_request_ms_count{method=POST,route=invoices.store,status=2xx}' => 100,
        ];

        $metrics = $this->createMock(MetricsService::class);
        $metrics->method('snapshot')->willReturnCallback(
            fn (?string $bucket = null) => $bucket === $today ? $histogram : [],
        );
        $this->app->instance(MetricsService::class, $metrics);

        $exit = \Illuminate\Support\Facades\Artisan::call('slo:report', ['--fail-on-breach' => true]);

        $this->assertSame(
            1,
            $exit,
            'PERF-SLO-3: a route class above its p95 budget must make --fail-on-breach exit non-zero.',
        );
        $this->assertStringContainsString('OUT OF SLO', \Illuminate\Support\Facades\Artisan::output());
    }
}
