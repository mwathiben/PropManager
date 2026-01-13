<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\PushSubscription;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    private ?WebPush $webPush = null;

    /**
     * Generate new VAPID keys
     */
    public function generateVapidKeys(): array
    {
        // Generate VAPID keys using openssl
        $privateKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        $details = openssl_pkey_get_details($privateKey);

        // Export private key
        openssl_pkey_export($privateKey, $privateKeyPem);

        // Get the public key coordinates
        $x = $details['ec']['x'];
        $y = $details['ec']['y'];

        // Create uncompressed public key (0x04 + x + y)
        $publicKeyBinary = chr(4).str_pad($x, 32, chr(0), STR_PAD_LEFT).str_pad($y, 32, chr(0), STR_PAD_LEFT);

        // Base64url encode
        $publicKey = $this->base64UrlEncode($publicKeyBinary);

        // For private key, we need to encode the 'd' parameter
        $d = $details['ec']['d'];
        $privateKeyEncoded = $this->base64UrlEncode(str_pad($d, 32, chr(0), STR_PAD_LEFT));

        return [
            'public' => $publicKey,
            'private' => $privateKeyEncoded,
        ];
    }

    /**
     * Save VAPID keys for a landlord
     */
    public function saveVapidKeys(int $landlordId, array $keys): void
    {
        Setting::set('vapid_public_key', $keys['public'], false, 'push', 'VAPID public key', $landlordId);
        Setting::set('vapid_private_key', $keys['private'], true, 'push', 'VAPID private key', $landlordId);
        Setting::set('vapid_subject', 'mailto:admin@propmanager.com', false, 'push', 'VAPID subject', $landlordId);
    }

    /**
     * Get VAPID public key for a landlord
     */
    public function getPublicKey(int $landlordId): ?string
    {
        return Setting::get('vapid_public_key', null, $landlordId);
    }

    /**
     * Check if VAPID keys are configured
     */
    public function isConfigured(int $landlordId): bool
    {
        $publicKey = Setting::get('vapid_public_key', null, $landlordId);
        $privateKey = Setting::get('vapid_private_key', null, $landlordId);

        return ! empty($publicKey) && ! empty($privateKey);
    }

    /**
     * Subscribe a user to push notifications
     */
    public function subscribe(int $userId, array $subscription): PushSubscription
    {
        return PushSubscription::createOrUpdate($userId, [
            'endpoint' => $subscription['endpoint'],
            'public_key' => $subscription['keys']['p256dh'] ?? $subscription['public_key'],
            'auth_token' => $subscription['keys']['auth'] ?? $subscription['auth_token'],
            'content_encoding' => $subscription['contentEncoding'] ?? 'aesgcm',
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Unsubscribe from push notifications
     */
    public function unsubscribe(string $endpoint): bool
    {
        $subscription = PushSubscription::findByEndpoint($endpoint);

        if ($subscription) {
            return $subscription->delete();
        }

        return false;
    }

    /**
     * Send push notification to a user
     */
    public function send(
        int $userId,
        string $title,
        string $body,
        ?array $data = null,
        ?int $landlordId = null
    ): bool {
        $subscriptions = PushSubscription::forUser($userId)->active()->get();

        if ($subscriptions->isEmpty()) {
            Log::info('No active push subscriptions for user', ['user_id' => $userId]);

            return false;
        }

        // Determine landlord ID
        if (! $landlordId) {
            $user = \App\Models\User::find($userId);
            $landlordId = $user->role === 'tenant' ? $user->landlord_id : $user->id;
        }

        // Get VAPID keys
        $publicKey = Setting::get('vapid_public_key', null, $landlordId);
        $privateKey = Setting::get('vapid_private_key', null, $landlordId);
        $subject = Setting::get('vapid_subject', 'mailto:admin@propmanager.com', $landlordId);

        if (! $publicKey || ! $privateKey) {
            Log::warning('VAPID keys not configured for push notifications', ['landlord_id' => $landlordId]);

            return false;
        }

        $payload = $this->createPayload($title, $body, $data);
        $success = false;

        foreach ($subscriptions as $subscription) {
            try {
                $result = $this->sendToSubscription(
                    $subscription,
                    $payload,
                    $publicKey,
                    $privateKey,
                    $subject
                );

                if ($result) {
                    $success = true;
                }
            } catch (\Exception $e) {
                Log::error('Push notification failed', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);

                // If subscription is invalid, remove it
                if ($this->isSubscriptionExpired($e)) {
                    $subscription->delete();
                }
            }
        }

        // Create notification record
        if ($landlordId) {
            Notification::create([
                'landlord_id' => $landlordId,
                'recipient_id' => $userId,
                'type' => 'general',
                'channel' => 'push',
                'subject' => $title,
                'message' => $body,
                'data' => $data,
                'status' => $success ? 'sent' : 'failed',
                'sent_at' => $success ? now() : null,
            ]);
        }

        return $success;
    }

    /**
     * Send to multiple users
     */
    public function sendToMultiple(
        array $userIds,
        string $title,
        string $body,
        ?array $data = null,
        ?int $landlordId = null
    ): array {
        $results = [
            'sent' => 0,
            'failed' => 0,
        ];

        foreach ($userIds as $userId) {
            if ($this->send($userId, $title, $body, $data, $landlordId)) {
                $results['sent']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Create the push payload
     */
    private function createPayload(string $title, string $body, ?array $data): string
    {
        $payload = [
            'title' => $title,
            'body' => $body,
            'icon' => '/images/icon-192.png',
            'badge' => '/images/badge-72.png',
            'timestamp' => time() * 1000,
            'data' => $data ?? [],
        ];

        // Add default action
        if (! isset($payload['data']['url'])) {
            $payload['data']['url'] = '/dashboard';
        }

        return json_encode($payload);
    }

    /**
     * Send notification to a specific subscription using web-push
     */
    private function sendToSubscription(
        PushSubscription $subscription,
        string $payload,
        string $publicKey,
        string $privateKey,
        string $subject
    ): bool {
        $webPush = $this->getWebPush($publicKey, $privateKey, $subject);

        $pushSubscription = Subscription::create([
            'endpoint' => $subscription->endpoint,
            'publicKey' => $subscription->public_key,
            'authToken' => $subscription->auth_token,
            'contentEncoding' => $subscription->content_encoding ?? 'aesgcm',
        ]);

        $webPush->queueNotification($pushSubscription, $payload);

        $results = $webPush->flush();

        foreach ($results as $report) {
            $endpoint = $report->getEndpoint();

            if ($report->isSuccess()) {
                Log::info('Push notification sent successfully', [
                    'endpoint' => substr($endpoint, 0, 50).'...',
                ]);

                return true;
            }

            Log::error('Push notification failed', [
                'endpoint' => substr($endpoint, 0, 50).'...',
                'reason' => $report->getReason(),
                'response' => $report->getResponse()?->getBody()?->getContents(),
            ]);

            if ($report->isSubscriptionExpired()) {
                $subscription->delete();
                Log::info('Removed expired push subscription', ['id' => $subscription->id]);
            }
        }

        return false;
    }

    /**
     * Get or create WebPush instance
     */
    private function getWebPush(string $publicKey, string $privateKey, string $subject): WebPush
    {
        if ($this->webPush === null) {
            $this->webPush = new WebPush([
                'VAPID' => [
                    'subject' => $subject,
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ]);

            $this->webPush->setReuseVAPIDHeaders(true);
        }

        return $this->webPush;
    }

    /**
     * Check if an exception indicates an expired subscription
     */
    private function isSubscriptionExpired(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'expired')
            || str_contains($message, 'unsubscribed')
            || str_contains($message, '410')
            || str_contains($message, '404');
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Get all subscriptions for a user
     */
    public function getUserSubscriptions(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return PushSubscription::forUser($userId)->active()->get();
    }

    /**
     * Cleanup expired subscriptions
     */
    public function cleanupExpired(): int
    {
        return PushSubscription::cleanupExpired();
    }
}
