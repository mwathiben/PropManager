<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase-25 API-RATELIMIT-1 watchdog: every throttled API response must
 * carry the X-RateLimit envelope (Limit + Remaining + Reset).
 *
 * Laravel's ThrottleRequests already emits Limit + Remaining on every
 * throttled response — this suite proves the integration. Reset is the
 * piece ApiRateLimitHeaders middleware fills in on 200 (Laravel only
 * emits it natively on 429).
 */
class Phase25RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_api_response_carries_full_ratelimit_envelope(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['tenant:read']);

        // /api/v1/tenant/lease — simple authenticated GET that
        // requires tenant:read; returns 200 or 404 depending on lease
        // state, both pass through the throttle bucket.
        $response = $this->getJson('/api/v1/tenant/lease');

        $this->assertContains(
            $response->status(),
            [200, 404],
            'unexpected status from /api/v1/tenant/lease: '.$response->status(),
        );
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
        $response->assertHeader('X-RateLimit-Reset');

        $reset = (int) $response->headers->get('X-RateLimit-Reset');
        $this->assertGreaterThan(
            time(),
            $reset,
            'API-RATELIMIT-1: X-RateLimit-Reset must be a future epoch second.',
        );
        $this->assertLessThanOrEqual(
            time() + 120,
            $reset,
            'API-RATELIMIT-1: X-RateLimit-Reset must be within the bucket decay window (60s + slack).',
        );
    }

    public function test_middleware_does_not_overwrite_existing_reset(): void
    {
        // Source-level guard: when Laravel's ThrottleRequests sets
        // X-RateLimit-Reset (it does on 429s with the exact bucket
        // refill timestamp), the middleware must defer to Laravel's
        // value. A 200 path has no Reset yet — that's the gap this
        // middleware fills with a conservative `now + decay` estimate.
        $source = file_get_contents(app_path('Http/Middleware/ApiRateLimitHeaders.php'));

        $this->assertStringContainsString(
            "headers->has('X-RateLimit-Reset')",
            $source,
            'API-RATELIMIT-1: middleware must gate on the presence of X-RateLimit-Reset before emitting its own.',
        );
        $this->assertStringContainsString(
            "is('api/*')",
            $source,
            'API-RATELIMIT-1: middleware must short-circuit for non-API paths.',
        );
    }

    public function test_429_response_still_carries_envelope_from_laravel(): void
    {
        // Drive a 429 from the login bucket (5/min) — confirm Laravel's
        // existing 429 envelope is preserved (we are NOT replacing it,
        // only supplementing the 200 path).
        $payload = ['email' => 'nobody@example.test', 'password' => 'bad', 'device_name' => 't'];

        // Hit the bucket 6 times to force a 429 on the 6th.
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/v1/auth/login', $payload);
        }

        $response->assertStatus(429);
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining', '0');
        $response->assertHeader('X-RateLimit-Reset');
        $response->assertHeader('Retry-After');
    }

    public function test_middleware_is_registered_in_api_group(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringContainsString(
            'App\\Http\\Middleware\\ApiRateLimitHeaders::class',
            $bootstrap,
            'API-RATELIMIT-1: ApiRateLimitHeaders must be registered in the api middleware group.',
        );
    }
}
