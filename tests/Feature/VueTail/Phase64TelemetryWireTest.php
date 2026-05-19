<?php

declare(strict_types=1);

namespace Tests\Feature\VueTail;

use App\Http\Controllers\Api\PwaTelemetryController;
use App\Models\User;
use App\Services\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * Phase-64 TELEMETRY-WIRE-1/2/3 watchdog: PWA gauge ingress endpoint
 * + client-side sendBeacon transmission + alert-thresholds.md update.
 */
class Phase64TelemetryWireTest extends TestCase
{
    use RefreshDatabase;

    public function test_telemetry_endpoint_accepts_allowed_metric(): void
    {
        $user = User::factory()->create();

        $mock = Mockery::mock(MetricsService::class);
        $mock->shouldReceive('gauge')
            ->once()
            ->withArgs(function (string $metric, int $value, array $labels) {
                return $metric === 'offline_shell_boot_count'
                    && $value === 3
                    && $labels === ['platform' => 'android'];
            });
        $this->app->instance(MetricsService::class, $mock);

        $response = $this->actingAs($user, 'sanctum')->postJson(
            '/api/v1/telemetry/pwa',
            [
                'metric' => 'offline_shell_boot_count',
                'value' => 3,
                'labels' => ['platform' => 'android'],
            ],
        );

        $response->assertNoContent();
    }

    public function test_telemetry_endpoint_rejects_unknown_metric(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson(
            '/api/v1/telemetry/pwa',
            ['metric' => 'evil_arbitrary_metric', 'value' => 1],
        );

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'unknown_metric']);
    }

    public function test_telemetry_endpoint_rejects_invalid_label_key(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson(
            '/api/v1/telemetry/pwa',
            [
                'metric' => 'offline_shell_boot_count',
                'value' => 1,
                'labels' => ['Bad-Key' => 'foo'],
            ],
        );

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'invalid_label_key']);
    }

    public function test_telemetry_endpoint_rejects_negative_value(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson(
            '/api/v1/telemetry/pwa',
            ['metric' => 'offline_shell_boot_count', 'value' => -1],
        );

        $response->assertStatus(422);
    }

    public function test_telemetry_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/telemetry/pwa', [
            'metric' => 'offline_shell_boot_count',
            'value' => 1,
        ]);

        // Sanctum unauthenticated responses are 401.
        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_telemetry_rate_limiter_registered(): void
    {
        $resolver = RateLimiter::limiter('telemetry');
        $this->assertNotNull($resolver);
    }

    public function test_pwa_telemetry_controller_has_three_allowed_metrics(): void
    {
        $this->assertCount(3, PwaTelemetryController::ALLOWED_METRICS);
        $this->assertContains('offline_writes_dead_letter_count', PwaTelemetryController::ALLOWED_METRICS);
        $this->assertContains('offline_photo_quota_evictions_count', PwaTelemetryController::ALLOWED_METRICS);
        $this->assertContains('offline_shell_boot_count', PwaTelemetryController::ALLOWED_METRICS);
    }

    public function test_pwa_telemetry_ts_uses_send_beacon_on_visibility_change(): void
    {
        $contents = file_get_contents(base_path('resources/js/lib/pwaTelemetry.ts'));

        foreach (['sendBeacon', 'visibilitychange', 'beforeunload', 'registerPwaTelemetry', 'flush', 'increment'] as $token) {
            $this->assertStringContainsString(
                $token,
                $contents,
                "pwaTelemetry.ts missing expected token '{$token}'",
            );
        }
    }

    public function test_app_js_registers_pwa_telemetry_on_boot(): void
    {
        $contents = file_get_contents(base_path('resources/js/app.js'));

        $this->assertStringContainsString("'@/lib/pwaTelemetry'", $contents);
        $this->assertStringContainsString('registerPwaTelemetry', $contents);
    }

    public function test_alert_thresholds_documents_wired_endpoint(): void
    {
        $contents = file_get_contents(base_path('docs/runbooks/alert-thresholds.md'));

        $this->assertStringContainsString('Phase-64 TELEMETRY-WIRE', $contents);
        $this->assertStringContainsString('/api/v1/telemetry/pwa', $contents);
        $this->assertStringContainsString('sendBeacon', $contents);
    }
}
