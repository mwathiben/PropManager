<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalDocument extends Model
{
    /**
     * Document type constants.
     */
    public const TYPE_TERMS = 'terms';

    public const TYPE_PRIVACY = 'privacy';

    public const TYPE_COOKIES = 'cookies';

    public const TYPE_DPA = 'dpa'; // Data Processing Agreement

    protected $fillable = [
        'type',
        'version',
        'title',
        'content',
        'summary',
        'is_active',
        'effective_date',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'effective_date' => 'date',
    ];

    /**
     * Get the user who created this document.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the active version of a document type.
     *
     * Ordered by effective_date desc then id desc — deterministic, and immune to
     * lexicographic version comparisons (e.g. '10.0' < '9.0' alphabetically).
     */
    public static function getActive(string $type): ?self
    {
        return self::where('type', $type)
            ->where('is_active', true)
            ->where('effective_date', '<=', now())
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Publish a new version (deactivates previous versions).
     */
    public static function publish(
        string $type,
        string $version,
        string $title,
        string $content,
        ?string $summary = null,
        ?\DateTimeInterface $effectiveDate = null,
        ?int $createdBy = null
    ): self {
        // Deactivate all previous versions
        self::where('type', $type)->update(['is_active' => false]);

        return self::create([
            'type' => $type,
            'version' => $version,
            'title' => $title,
            'content' => $content,
            'summary' => $summary,
            'is_active' => true,
            'effective_date' => $effectiveDate ?? now(),
            'created_by' => $createdBy ?? auth()->id(),
        ]);
    }

    /**
     * Get all versions of a document type.
     */
    public static function getVersionHistory(string $type): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('type', $type)
            ->orderBy('version', 'desc')
            ->get();
    }

    /**
     * Check if there's a newer version than what user has consented to.
     */
    public static function hasNewerVersion(string $type, string $consentedVersion): bool
    {
        $activeDoc = self::getActive($type);
        if (! $activeDoc) {
            return false;
        }

        return version_compare($activeDoc->version, $consentedVersion, '>');
    }

    /**
     * Get human-readable type name.
     */
    public function getTypeNameAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_TERMS => 'Terms of Service',
            self::TYPE_PRIVACY => 'Privacy Policy',
            self::TYPE_COOKIES => 'Cookie Policy',
            self::TYPE_DPA => 'Data Processing Agreement',
            default => ucfirst($this->type),
        };
    }

    /**
     * Scope: Active documents.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('effective_date', '<=', now());
    }

    /**
     * Scope: By type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
