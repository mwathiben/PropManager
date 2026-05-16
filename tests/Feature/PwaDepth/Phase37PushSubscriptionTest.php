<?php

declare(strict_types=1);

namespace Tests\Feature\PwaDepth;

use App\Models\PushSubscription;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-37 PWA-PUSH-FE-1/3: backend contract the useWebPush composable
 * consumes — VAPID key endpoint shape, subscribe/unsubscribe round
 * trip, and per-landlord key isolation (so server-driven rotation in
 * refreshKey() actually surfaces a different public_key).
 *
 * Tests use static fake VAPID strings instead of PushNotificationService
 * ::generateVapidKeys() because openssl_pkey_new() with EC params fails
 * on the Windows test env (missing openssl.cnf). The composable only
 * cares that the endpoint returns whatever string Setting::get returns
 * — generation correctness is covered by production VAPID rotation.
 */
class Phase37PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function seedVapidKeys(int $landlordId, string $publicKey, string $privateKey): void
    {
        Setting::set('vapid_public_key', $publicKey, false, 'push', 'VAPID public key', $landlordId);
        Setting::set('vapid_private_key', $privateKey, true, 'push', 'VAPID private key', $landlordId);
    }

    public function test_vapid_key_endpoint_returns_public_key_for_authenticated_landlord(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->seedVapidKeys($landlord->id, 'BHc-fake-public', 'fake-private');

        $response = $this->actingAs($landlord)->getJson(route('notifications.push.key'));

        $response->assertOk();
        $this->assertSame('BHc-fake-public', $response->json('public_key'));
    }

    public function test_vapid_key_endpoint_returns_null_when_not_configured(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($landlord)->getJson(route('notifications.push.key'));

        $response->assertOk();
        $this->assertNull($response->json('public_key'));
    }

    public function test_subscribe_persists_push_subscription_row(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $payload = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/example-token',
            'keys' => [
                'p256dh' => 'BHc-fake-p256dh-public-key',
                'auth' => 'fake-auth-token',
            ],
        ];

        $response = $this->actingAs($landlord)->postJson(
            route('notifications.push.subscribe'),
            $payload,
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $landlord->id,
            'endpoint' => $payload['endpoint'],
            'public_key' => $payload['keys']['p256dh'],
            'auth_token' => $payload['keys']['auth'],
        ]);
    }

    public function test_unsubscribe_removes_push_subscription_row(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $endpoint = 'https://fcm.googleapis.com/fcm/send/another-token';
        PushSubscription::create([
            'user_id' => $landlord->id,
            'endpoint' => $endpoint,
            'public_key' => 'BHc-p256dh',
            'auth_token' => 'auth',
            'content_encoding' => 'aesgcm',
            'user_agent' => 'test',
        ]);

        $response = $this->actingAs($landlord)->postJson(
            route('notifications.push.unsubscribe'),
            ['endpoint' => $endpoint],
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => $endpoint]);
    }

    public function test_rotated_vapid_keys_surface_new_public_key(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->seedVapidKeys($landlord->id, 'BHc-original-public', 'original-private');

        $beforeRotation = $this->actingAs($landlord)
            ->getJson(route('notifications.push.key'))
            ->json('public_key');

        $this->seedVapidKeys($landlord->id, 'BHc-rotated-public', 'rotated-private');

        $afterRotation = $this->actingAs($landlord)
            ->getJson(route('notifications.push.key'))
            ->json('public_key');

        $this->assertSame('BHc-original-public', $beforeRotation);
        $this->assertSame('BHc-rotated-public', $afterRotation);
        $this->assertNotSame($beforeRotation, $afterRotation);
    }

    public function test_vapid_key_isolated_per_landlord(): void
    {
        $landlordA = User::factory()->create(['role' => 'landlord']);
        $landlordB = User::factory()->create(['role' => 'landlord']);
        $this->seedVapidKeys($landlordA->id, 'BHc-public-A', 'private-A');
        $this->seedVapidKeys($landlordB->id, 'BHc-public-B', 'private-B');

        $publicA = $this->actingAs($landlordA)
            ->getJson(route('notifications.push.key'))
            ->json('public_key');
        $publicB = $this->actingAs($landlordB)
            ->getJson(route('notifications.push.key'))
            ->json('public_key');

        $this->assertSame('BHc-public-A', $publicA);
        $this->assertSame('BHc-public-B', $publicB);
        $this->assertNotSame($publicA, $publicB);
    }

    public function test_push_endpoints_reject_unauthenticated(): void
    {
        $this->getJson(route('notifications.push.key'))->assertUnauthorized();
        $this->postJson(route('notifications.push.subscribe'), [
            'endpoint' => 'x',
            'keys' => ['p256dh' => 'x', 'auth' => 'x'],
        ])->assertUnauthorized();
    }
}
