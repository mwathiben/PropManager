<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\DeliverWebhookJob;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase-25 API-WEBHOOK-1/2/3 watchdog: outbound webhook subscriptions,
 * delivery log + retry, event catalog.
 */
class Phase25WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_landlord_can_register_a_webhook_subscription(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)->post(route('settings.webhooks.store'), [
            'url' => 'https://example.test/hooks/propmanager',
            'events' => ['payment.received', 'invoice.created'],
        ])->assertRedirect();

        $this->assertSame(1, WebhookSubscription::count());
        $sub = WebhookSubscription::query()->withoutGlobalScope('landlord')->first();
        $this->assertSame($landlord->id, $sub->landlord_id);
        $this->assertSame(['payment.received', 'invoice.created'], $sub->events);
        $this->assertTrue($sub->active);
        $this->assertNotEmpty($sub->secret, 'API-WEBHOOK-1: secret must be generated server-side.');
    }

    public function test_store_rejects_non_https_urls(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)->post(route('settings.webhooks.store'), [
            'url' => 'http://example.test/hooks',
            'events' => ['payment.received'],
        ])->assertSessionHasErrors('url');

        $this->assertSame(0, WebhookSubscription::count());
    }

    public function test_store_rejects_unknown_event_types(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)->post(route('settings.webhooks.store'), [
            'url' => 'https://example.test/hooks',
            'events' => ['unknown.event'],
        ])->assertSessionHasErrors('events.0');

        $this->assertSame(0, WebhookSubscription::count());
    }

    public function test_tenant_role_cannot_access_webhook_ui(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);

        $this->actingAs($tenant)
            ->get(route('settings.webhooks.index'))
            ->assertForbidden();
    }

    public function test_event_catalog_is_populated(): void
    {
        $events = config('webhooks.events');

        $this->assertIsArray($events);
        $this->assertNotEmpty($events, 'API-WEBHOOK-3: event catalog must list at least one event type.');

        foreach (['payment.received', 'invoice.created', 'lease.signed'] as $core) {
            $this->assertArrayHasKey(
                $core,
                $events,
                "API-WEBHOOK-3: event catalog must include '{$core}'.",
            );
        }
    }

    public function test_successful_dispatch_writes_delivery_row_and_hmac_signature(): void
    {
        Http::fake([
            'example.test/*' => Http::response('ok', 200),
        ]);

        $landlord = User::factory()->create(['role' => 'landlord']);
        $sub = WebhookSubscription::create([
            'landlord_id' => $landlord->id,
            'url' => 'https://example.test/hook',
            'secret' => 'test-secret-32-bytes',
            'events' => ['payment.received'],
            'active' => true,
        ]);

        (new DeliverWebhookJob($sub->id, 'payment.received', ['amount' => 1000]))->handle();

        $delivery = WebhookDelivery::query()->first();
        $this->assertNotNull($delivery, 'API-WEBHOOK-2: every dispatch must write a delivery row.');
        $this->assertSame(200, $delivery->http_status);
        $this->assertSame('payment.received', $delivery->event_type);
        $this->assertFalse($delivery->dead_lettered);

        Http::assertSent(function ($request) {
            $sig = $request->header('X-PropManager-Signature')[0] ?? null;
            $event = $request->header('X-PropManager-Event')[0] ?? null;

            return str_starts_with((string) $sig, 'sha256=')
                && $event === 'payment.received';
        });
    }

    public function test_failed_dispatch_schedules_retry_under_max_attempts(): void
    {
        Http::fake([
            'example.test/*' => Http::response('boom', 500),
        ]);
        Queue::fake();

        $landlord = User::factory()->create(['role' => 'landlord']);
        $sub = WebhookSubscription::create([
            'landlord_id' => $landlord->id,
            'url' => 'https://example.test/hook',
            'secret' => 'test-secret',
            'events' => ['payment.received'],
            'active' => true,
        ]);

        (new DeliverWebhookJob($sub->id, 'payment.received', ['x' => 1], attempt: 1))->handle();

        Queue::assertPushed(DeliverWebhookJob::class, function ($job) {
            return $job->attempt === 2;
        });

        $delivery = WebhookDelivery::query()->first();
        $this->assertSame(500, $delivery->http_status);
        $this->assertFalse($delivery->dead_lettered);
    }

    public function test_dispatch_dead_letters_at_max_attempts(): void
    {
        Http::fake([
            'example.test/*' => Http::response('still broken', 500),
        ]);

        $landlord = User::factory()->create(['role' => 'landlord']);
        $sub = WebhookSubscription::create([
            'landlord_id' => $landlord->id,
            'url' => 'https://example.test/hook',
            'secret' => 'test-secret',
            'events' => ['payment.received'],
            'active' => true,
        ]);

        (new DeliverWebhookJob($sub->id, 'payment.received', ['x' => 1], attempt: WebhookDelivery::MAX_ATTEMPTS))->handle();

        $delivery = WebhookDelivery::query()->first();
        $this->assertTrue(
            $delivery->dead_lettered,
            'API-WEBHOOK-2: 5th failed attempt must mark the row dead_lettered.',
        );
    }

    public function test_inactive_subscription_does_not_dispatch(): void
    {
        Http::fake();

        $landlord = User::factory()->create(['role' => 'landlord']);
        $sub = WebhookSubscription::create([
            'landlord_id' => $landlord->id,
            'url' => 'https://example.test/hook',
            'secret' => 'test-secret',
            'events' => ['payment.received'],
            'active' => false,
        ]);

        (new DeliverWebhookJob($sub->id, 'payment.received', ['x' => 1]))->handle();

        $this->assertSame(0, WebhookDelivery::count(), 'API-WEBHOOK-1: inactive subscription must not deliver.');
        Http::assertNothingSent();
    }
}
