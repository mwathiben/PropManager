<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
        'user_agent',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user this subscription belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the subscription is expired
     */
    public function isExpired(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Scope to get non-expired subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope to get subscriptions for a specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get subscription data in the format needed for web-push
     */
    public function toWebPushArray(): array
    {
        return [
            'endpoint' => $this->endpoint,
            'keys' => [
                'p256dh' => $this->public_key,
                'auth' => $this->auth_token,
            ],
            'contentEncoding' => $this->content_encoding,
        ];
    }

    /**
     * Find subscription by endpoint
     */
    public static function findByEndpoint(string $endpoint): ?self
    {
        return static::where('endpoint', $endpoint)->first();
    }

    /**
     * Create or update a subscription
     */
    public static function createOrUpdate(int $userId, array $subscriptionData): self
    {
        return static::updateOrCreate(
            ['endpoint' => $subscriptionData['endpoint']],
            [
                'user_id' => $userId,
                'public_key' => $subscriptionData['public_key'],
                'auth_token' => $subscriptionData['auth_token'],
                'content_encoding' => $subscriptionData['content_encoding'] ?? 'aesgcm',
                'user_agent' => $subscriptionData['user_agent'] ?? null,
                'expires_at' => $subscriptionData['expires_at'] ?? null,
            ]
        );
    }

    /**
     * Remove expired subscriptions
     */
    public static function cleanupExpired(): int
    {
        return static::where('expires_at', '<', now())->delete();
    }
}
