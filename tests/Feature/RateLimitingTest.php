<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear all rate limiters before each test
        RateLimiter::clear('export');
        RateLimiter::clear('search');
        RateLimiter::clear('api');
    }

    public function test_export_endpoint_returns_rate_limit_headers(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($landlord)
            ->get(route('finances.invoices.export'));

        // Export endpoint should return rate limit headers
        $response->assertHeader('X-RateLimit-Limit', '5');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_export_endpoint_enforces_rate_limit(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        // Make 5 requests (the limit)
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($landlord)
                ->get(route('finances.deposits.export'));
        }

        // 6th request should be rate limited
        $response = $this->actingAs($landlord)
            ->get(route('finances.deposits.export'));

        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
        $response->assertJson([
            'message' => 'Too many export requests. Please wait before exporting again.',
        ]);
    }

    public function test_help_search_enforces_rate_limit(): void
    {
        $user = User::factory()->create();

        // Make 30 requests (the limit)
        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($user)
                ->get(route('help.search', ['q' => 'test']));
        }

        // 31st request should be rate limited
        $response = $this->actingAs($user)
            ->get(route('help.search', ['q' => 'test']));

        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
        $response->assertJson([
            'message' => 'Too many search requests. Please slow down.',
        ]);
    }

    public function test_help_search_endpoint_uses_search_rate_limiter(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('help.search', ['q' => 'test']));

        // Should use the search rate limiter
        $response->assertHeader('X-RateLimit-Limit', '30');
    }

    public function test_banks_api_endpoint_uses_bank_verify_rate_limiter(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($landlord)
            ->get(route('payments-hub.banks'));

        // RATE-10: bank-verify limiter (3/min) is tighter than the
        // generic api throttle since both /banks and /verify-account
        // round-trip to Paystack and the verify response leaks names.
        $response->assertHeader('X-RateLimit-Limit', '3');
    }

    public function test_audit_logs_export_uses_export_rate_limiter(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($landlord)
            ->get(route('audit-logs.export'));

        // Should use the export rate limiter (5/min)
        $response->assertHeader('X-RateLimit-Limit', '5');
    }

    public function test_rate_limiters_are_user_specific(): void
    {
        $landlord1 = User::factory()->create(['role' => 'landlord']);
        $landlord2 = User::factory()->create(['role' => 'landlord']);

        // Landlord 1 makes 5 export requests (hits limit)
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($landlord1)
                ->get(route('finances.invoices.export'));
        }

        // Landlord 1 should be rate limited
        $response1 = $this->actingAs($landlord1)
            ->get(route('finances.invoices.export'));
        $response1->assertStatus(429);

        // Landlord 2 should NOT be rate limited (separate user)
        $response2 = $this->actingAs($landlord2)
            ->get(route('finances.invoices.export'));
        $response2->assertStatus(200);
    }
}
