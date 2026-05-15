<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase-25 API-ERROR-1 + RATELIMIT-2 watchdog: every /api/* error
 * response conforms to RFC 7807 problem+json.
 */
class Phase25ErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_error_is_problem_json(): void
    {
        // POST without device_name (required field) on /v1/auth/login
        // — Laravel's ValidationException is rerendered as problem+json.
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJsonStructure(['type', 'title', 'status', 'detail', 'instance', 'errors']);
        $response->assertJson(['status' => 422, 'title' => 'Validation failed']);
        $this->assertStringContainsString(
            'validation-failed',
            $response->json('type'),
            'API-ERROR-1: validation error must use the validation-failed type URI.',
        );
    }

    public function test_unauthenticated_error_is_problem_json(): void
    {
        $response = $this->getJson('/api/v1/auth/user');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJsonStructure(['type', 'title', 'status', 'detail', 'instance']);
        $response->assertJson(['status' => 401, 'title' => 'Unauthenticated']);
    }

    public function test_forbidden_ability_returns_problem_json(): void
    {
        // A token with only tenant:read should be denied on a
        // landlord:manage endpoint.
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['tenant:read']);

        $response = $this->getJson('/api/v1/landlord/properties');

        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJson(['status' => 403, 'title' => 'Forbidden']);
    }

    public function test_429_response_is_problem_json_with_retry_after_seconds(): void
    {
        // Bucket: 5/min on the login limiter. Hit it 6 times.
        $payload = ['email' => 'nobody@example.test', 'password' => 'bad', 'device_name' => 't'];
        $response = null;
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/v1/auth/login', $payload);
        }

        $response->assertStatus(429);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJsonStructure(['type', 'title', 'status', 'detail', 'instance', 'retry_after_seconds']);
        $response->assertJson(['status' => 429, 'title' => 'Too many requests']);

        $retry = $response->json('retry_after_seconds');
        $this->assertIsInt($retry);
        $this->assertGreaterThan(0, $retry, 'API-RATELIMIT-2: retry_after_seconds must be a positive integer.');

        // Laravel's existing throttle headers still flow.
        $response->assertHeader('Retry-After');
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining', '0');
    }

    public function test_non_api_routes_are_unaffected(): void
    {
        // The web error renderer (Inertia 403 page, etc.) MUST not get
        // hijacked into problem+json. The renderer is gated to api/*.
        $response = $this->get('/dashboard');

        // Authenticated state would 200; unauthenticated redirects to login.
        $this->assertNotEquals(
            'application/problem+json',
            $response->headers->get('Content-Type'),
            'API-ERROR-1: non-API routes must NOT receive problem+json responses.',
        );
    }
}
