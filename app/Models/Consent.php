<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consent extends Model
{
    /**
     * Consent type constants.
     */
    public const TYPE_TERMS = 'terms';

    public const TYPE_PRIVACY = 'privacy';

    public const TYPE_MARKETING = 'marketing';

    public const TYPE_DATA_PROCESSING = 'data_processing';

    public const TYPE_THIRD_PARTY_SHARING = 'third_party_sharing';

    protected $fillable = [
        'user_id',
        'consent_type',
        'version',
        'is_granted',
        'granted_at',
        'withdrawn_at',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'is_granted' => 'boolean',
        'granted_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user who gave consent.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if consent is currently active.
     */
    public function isActive(): bool
    {
        return $this->is_granted && is_null($this->withdrawn_at);
    }

    /**
     * Withdraw consent.
     */
    public function withdraw(): bool
    {
        if (! $this->is_granted) {
            return false;
        }

        $this->update([
            'is_granted' => false,
            'withdrawn_at' => now(),
        ]);

        // Log withdrawal
        AuditLog::create([
            'user_id' => $this->user_id,
            'event_type' => 'consent_withdrawn',
            'auditable_type' => self::class,
            'auditable_id' => $this->id,
            'metadata' => [
                'consent_type' => $this->consent_type,
                'version' => $this->version,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return true;
    }

    /**
     * Record a new consent.
     */
    public static function record(
        User $user,
        string $type,
        string $version,
        ?array $metadata = null
    ): self {
        // Withdraw any previous consent of this type
        self::where('user_id', $user->id)
            ->where('consent_type', $type)
            ->where('is_granted', true)
            ->whereNull('withdrawn_at')
            ->update(['withdrawn_at' => now()]);

        $consent = self::create([
            'user_id' => $user->id,
            'consent_type' => $type,
            'version' => $version,
            'is_granted' => true,
            'granted_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
        ]);

        // Log the consent
        AuditLog::create([
            'user_id' => $user->id,
            'event_type' => 'consent_granted',
            'auditable_type' => self::class,
            'auditable_id' => $consent->id,
            'metadata' => [
                'consent_type' => $type,
                'version' => $version,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $consent;
    }

    /**
     * Check if user has valid consent of a specific type.
     */
    public static function hasValidConsent(User $user, string $type, ?string $version = null): bool
    {
        $query = self::where('user_id', $user->id)
            ->where('consent_type', $type)
            ->where('is_granted', true)
            ->whereNull('withdrawn_at');

        if ($version) {
            $query->where('version', $version);
        }

        return $query->exists();
    }

    /**
     * Get current consent version for a type.
     */
    public static function getCurrentVersion(string $type): ?string
    {
        $document = LegalDocument::where('type', $type)
            ->where('is_active', true)
            ->latest('version')
            ->first();

        return $document?->version;
    }

    /**
     * Get all consent types that are required.
     */
    public static function getRequiredTypes(): array
    {
        return config('security.compliance.consent_required', [
            self::TYPE_TERMS,
            self::TYPE_PRIVACY,
        ]);
    }

    /**
     * Check if user has all required consents.
     */
    public static function hasAllRequiredConsents(User $user): bool
    {
        foreach (self::getRequiredTypes() as $type) {
            $currentVersion = self::getCurrentVersion($type);
            if ($currentVersion && ! self::hasValidConsent($user, $type, $currentVersion)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get missing required consents for a user.
     */
    public static function getMissingConsents(User $user): array
    {
        $missing = [];

        foreach (self::getRequiredTypes() as $type) {
            $currentVersion = self::getCurrentVersion($type);
            if ($currentVersion && ! self::hasValidConsent($user, $type, $currentVersion)) {
                $missing[] = [
                    'type' => $type,
                    'version' => $currentVersion,
                ];
            }
        }

        return $missing;
    }

    /**
     * Scope: Active consents.
     */
    public function scopeActive($query)
    {
        return $query->where('is_granted', true)->whereNull('withdrawn_at');
    }

    /**
     * Scope: By type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('consent_type', $type);
    }
}
