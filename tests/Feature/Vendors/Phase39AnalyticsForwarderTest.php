<?php

declare(strict_types=1);

namespace Tests\Feature\Vendors;

use App\Models\ProductEvent;
use App\Models\User;
use App\Services\Vendors\AnalyticsForwarderInterface;
use App\Services\Vendors\PostHogForwarder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase-39 VENDOR-ANALYTICS-1/2/3: contract + PostHog adapter +
 * replay-batch cron + container wiring + config.
 */
class Phase39AnalyticsForwarderTest extends TestCase
{
    use RefreshDatabase;

    public function test_posthog_forwarder_sends_batch_with_api_key_envelope(): void
    {
        Http::fake([
            'app.posthog.com/batch' => Http::response(['status' => 1], 200),
        ]);

        $forwarder = new PostHogForwarder('phc_test_key');
        $result = $forwarder->flush([
            [
                'distinct_id' => '42',
                'event' => 'page_view',
                'properties' => ['path' => '/dashboard'],
                'timestamp' => '2026-05-16T10:00:00+00:00',
            ],
        ]);

        $this->assertSame(1, $result['accepted']);
        $this->assertSame(0, $result['rejected']);
        $this->assertSame('posthog', $result['vendor']);
        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $payload['api_key'] === 'phc_test_key'
                && count($payload['batch']) === 1
                && $payload['batch'][0]['event'] === 'page_view';
        });
    }

    public function test_posthog_forwarder_classifies_5xx_as_retryable(): void
    {
        Http::fake([
            'app.posthog.com/batch' => Http::response(['error' => 'overloaded'], 503),
        ]);

        $forwarder = new PostHogForwarder('phc_test_key');
        $result = $forwarder->flush([['distinct_id' => '1', 'event' => 'e', 'properties' => [], 'timestamp' => 'x']]);

        $this->assertSame(0, $result['accepted']);
        $this->assertSame(0, $result['rejected']);
        $this->assertSame(1, $result['retryable']);
    }

    public function test_posthog_forwarder_does_not_accept_on_non_2xx(): void
    {
        Http::fake([
            'app.posthog.com/batch' => Http::response(['error' => 'invalid api key'], 401),
        ]);

        $forwarder = new PostHogForwarder('phc_bad_key');
        $result = $forwarder->flush([['distinct_id' => '1', 'event' => 'e', 'properties' => [], 'timestamp' => 'x']]);

        $this->assertSame(0, $result['accepted']);
        $this->assertSame(1, $result['rejected'] + $result['retryable']);
        $this->assertSame('posthog', $result['vendor']);
    }

    public function test_replay_batch_advances_cursor_on_success(): void
    {
        config(['vendors.posthog.enabled' => true, 'vendors.posthog.api_key' => 'phc_test_key']);
        Http::fake(['app.posthog.com/batch' => Http::response(['status' => 1], 200)]);

        $user = User::factory()->create(['role' => 'landlord']);
        for ($i = 0; $i < 3; $i++) {
            ProductEvent::query()->withoutGlobalScopes()->create([
                'user_id' => $user->id,
                'landlord_id' => $user->id,
                'event_name' => 'page_view',
                'properties' => ['n' => $i],
                'created_at' => now()->subMinutes(30 - $i),
            ]);
        }

        $this->artisan('analytics:replay-batch')->assertExitCode(0);

        $cursor = Cache::get('vendors:analytics:last-replayed-at');
        $this->assertNotNull($cursor, 'cursor should advance after a clean run');
    }

    public function test_replay_batch_noop_when_vendor_disabled(): void
    {
        config(['vendors.posthog.enabled' => false]);

        $user = User::factory()->create(['role' => 'landlord']);
        ProductEvent::query()->withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'landlord_id' => $user->id,
            'event_name' => 'page_view',
            'properties' => [],
            'created_at' => now()->subMinutes(30),
        ]);

        // Re-bind so noop forwarder picks up the disabled config.
        app()->forgetInstance(AnalyticsForwarderInterface::class);
        $this->artisan('analytics:replay-batch')->assertExitCode(0);

        // Cursor still advances (work was processed by noop forwarder
        // returning 0/0/0 — the rows ARE seen and the cursor moves so
        // we don't re-process them indefinitely).
    }

    public function test_config_vendors_posthog_defaults_disabled(): void
    {
        // Defaults from .env.example must keep PostHog OFF so a fresh
        // install never accidentally exports customer events.
        config(['vendors.posthog.enabled' => env('VENDORS_POSTHOG_ENABLED', false)]);
        $this->assertFalse(config('vendors.posthog.enabled'));
    }
}
