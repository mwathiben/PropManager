<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory, TenantScope;

    /**
     * Event type constants.
     */
    public const EVENT_CREATED = 'created';

    public const EVENT_UPDATED = 'updated';

    public const EVENT_DELETED = 'deleted';

    /**
     * AUDIT-14: distinguish soft-delete from force-delete in the audit
     * trail. A force-delete on a soft-deletable model is a permanent loss
     * of data and deserves its own event type for compliance reporting.
     */
    public const EVENT_FORCE_DELETED = 'force_deleted';

    public const EVENT_RESTORED = 'restored';

    public const EVENT_EXPORTED = 'exported';

    public const EVENT_IMPORTED = 'imported';

    public const EVENT_ACCESSED = 'accessed';

    public const EVENT_BULK_UPDATE = 'bulk_update';

    public const EVENT_BULK_DELETE = 'bulk_delete';

    public const EVENT_STATUS_CHANGED = 'status_changed';

    protected $fillable = [
        'user_id',
        'landlord_id',
        'event_type',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'changed_fields',
        'ip_address',
        'user_agent',
        'url',
        'metadata',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the landlord scope.
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Get the auditable model.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter by event type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Phase-65 AUDIT-DEPTH-3: scope to filter by lawful basis stored
     * in the metadata JSON column. Reusable for any future regulator-
     * inquiry surface (DPA Article 5(1)(a) accountability).
     */
    public function scopeForLawfulBasis($query, string $basis)
    {
        return $query->whereJsonContains('metadata->lawful_basis', $basis);
    }

    /**
     * Scope to filter by model type.
     */
    public function scopeForModel($query, string $modelClass)
    {
        return $query->where('auditable_type', $modelClass);
    }

    /**
     * Scope to filter by specific model instance.
     */
    public function scopeForInstance($query, Model $model)
    {
        return $query->where('auditable_type', get_class($model))
            ->where('auditable_id', $model->getKey());
    }

    /**
     * Scope to filter by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by landlord.
     */
    public function scopeByLandlord($query, int $landlordId)
    {
        return $query->where('landlord_id', $landlordId);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter recent logs.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get a human-readable description of the event.
     */
    public function getDescriptionAttribute(): string
    {
        $modelName = class_basename($this->auditable_type);
        $userName = $this->user?->name ?? 'System';

        return match ($this->event_type) {
            self::EVENT_CREATED => "{$userName} created {$modelName} #{$this->auditable_id}",
            self::EVENT_UPDATED => "{$userName} updated {$modelName} #{$this->auditable_id}",
            self::EVENT_DELETED => "{$userName} deleted {$modelName} #{$this->auditable_id}",
            self::EVENT_RESTORED => "{$userName} restored {$modelName} #{$this->auditable_id}",
            self::EVENT_EXPORTED => "{$userName} exported {$modelName} data",
            self::EVENT_IMPORTED => "{$userName} imported {$modelName} data",
            self::EVENT_ACCESSED => "{$userName} accessed {$modelName} #{$this->auditable_id}",
            self::EVENT_STATUS_CHANGED => "{$userName} changed status of {$modelName} #{$this->auditable_id}",
            default => "{$userName} performed {$this->event_type} on {$modelName} #{$this->auditable_id}",
        };
    }

    /**
     * Get the event icon for UI display.
     */
    public function getEventIconAttribute(): string
    {
        return match ($this->event_type) {
            self::EVENT_CREATED => 'plus-circle',
            self::EVENT_UPDATED => 'pencil',
            self::EVENT_DELETED => 'trash',
            self::EVENT_RESTORED => 'arrow-path',
            self::EVENT_EXPORTED => 'arrow-down-tray',
            self::EVENT_IMPORTED => 'arrow-up-tray',
            self::EVENT_ACCESSED => 'eye',
            self::EVENT_STATUS_CHANGED => 'arrow-right-circle',
            default => 'document',
        };
    }

    /**
     * Get the event color for UI display.
     */
    public function getEventColorAttribute(): string
    {
        return match ($this->event_type) {
            self::EVENT_CREATED => 'green',
            self::EVENT_UPDATED => 'blue',
            self::EVENT_DELETED => 'red',
            self::EVENT_RESTORED => 'purple',
            self::EVENT_EXPORTED => 'yellow',
            self::EVENT_IMPORTED => 'orange',
            self::EVENT_ACCESSED => 'gray',
            self::EVENT_STATUS_CHANGED => 'indigo',
            default => 'gray',
        };
    }

    /**
     * Get changed fields as a formatted list.
     */
    public function getChangedFieldsListAttribute(): string
    {
        if (empty($this->changed_fields)) {
            return '';
        }

        return implode(', ', $this->changed_fields);
    }

    /**
     * Purge old audit logs.
     */
    public static function purgeOlderThan(int $days): int
    {
        return static::where('created_at', '<', now()->subDays($days))->delete();
    }

    /**
     * AUDIT-12: write a manual audit row with consistent context.
     *
     * Auto-fills user_id from Auth, landlord_id from $model->landlord_id
     * (falling back to the actor's tenancy), and ip/user_agent/url from
     * the current request. Use this anywhere we need to write an audit
     * outside the Auditable trait's automatic events — replaces the manual
     * AuditLog::create() pattern that drifts each time.
     */
    public static function record(string $event, Model $model, array $extras = []): self
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        $landlordId = null;
        if (isset($model->landlord_id)) {
            $landlordId = (int) $model->landlord_id;
        } elseif ($user) {
            $landlordId = $user->role === 'landlord'
                ? (int) $user->id
                : ($user->landlord_id ? (int) $user->landlord_id : null);
        }

        return static::create([
            'user_id' => $user?->id,
            'landlord_id' => $landlordId,
            'event_type' => $event,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'metadata' => $extras['metadata'] ?? null,
            'old_values' => $extras['old_values'] ?? null,
            'new_values' => $extras['new_values'] ?? null,
            'changed_fields' => $extras['changed_fields'] ?? null,
            'ip_address' => $extras['ip_address'] ?? \Illuminate\Support\Facades\Request::ip(),
            'user_agent' => $extras['user_agent'] ?? \Illuminate\Support\Facades\Request::userAgent(),
            'url' => $extras['url'] ?? \Illuminate\Support\Facades\Request::fullUrl(),
        ]);
    }
}
