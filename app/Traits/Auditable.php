<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    /**
     * Fields to exclude from audit logging.
     */
    protected static array $auditExclude = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Boot the auditable trait.
     */
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            static::logAudit($model, AuditLog::EVENT_CREATED);
        });

        static::updated(function (Model $model) {
            if ($model->wasChanged()) {
                static::logAudit($model, AuditLog::EVENT_UPDATED);
            }
        });

        static::deleted(function (Model $model) {
            // AUDIT-14: distinguish force-delete from soft-delete on models
            // that use SoftDeletes. A force-delete is permanent data loss
            // and deserves its own audit event for compliance reporting.
            $event = method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
                ? AuditLog::EVENT_FORCE_DELETED
                : AuditLog::EVENT_DELETED;

            static::logAudit($model, $event);
        });

        // Handle soft delete restoration if the model uses SoftDeletes
        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                static::logAudit($model, AuditLog::EVENT_RESTORED);
            });
        }
    }

    /**
     * Log an audit event.
     */
    protected static function logAudit(Model $model, string $eventType): void
    {
        // Don't log during seeding or testing unless explicitly enabled
        if (app()->runningInConsole() && ! config('security.audit.log_in_console', false)) {
            return;
        }

        // Check if audit logging is enabled for this event type
        $loggedEvents = config('security.audit.logged_events', []);
        if (! empty($loggedEvents) && ! in_array($eventType, $loggedEvents)) {
            return;
        }

        $user = Auth::user();
        $excludeFields = array_merge(
            static::$auditExclude,
            $model->getAuditExclude()
        );

        $oldValues = null;
        $newValues = null;
        $changedFields = null;

        if ($eventType === AuditLog::EVENT_CREATED) {
            $newValues = static::filterAuditValues($model->getAttributes(), $excludeFields);
        } elseif ($eventType === AuditLog::EVENT_UPDATED) {
            $oldValues = static::filterAuditValues($model->getOriginal(), $excludeFields);
            $newValues = static::filterAuditValues($model->getAttributes(), $excludeFields);
            $changedFields = array_keys($model->getDirty());
            $changedFields = array_diff($changedFields, $excludeFields);
        } elseif ($eventType === AuditLog::EVENT_DELETED) {
            $oldValues = static::filterAuditValues($model->getAttributes(), $excludeFields);
        }

        AuditLog::create([
            'user_id' => $user?->id,
            'landlord_id' => static::getLandlordId($model, $user),
            'event_type' => $eventType,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => ! empty($changedFields) ? array_values($changedFields) : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
            'metadata' => $model->getAuditMetadata(),
        ]);
    }

    /**
     * Filter values to exclude sensitive fields.
     */
    protected static function filterAuditValues(array $values, array $excludeFields): array
    {
        return array_diff_key($values, array_flip($excludeFields));
    }

    /**
     * Get the landlord ID for the audit log.
     */
    protected static function getLandlordId(Model $model, ?object $user): ?int
    {
        // If the model has a landlord_id, use it
        if (isset($model->landlord_id)) {
            return $model->landlord_id;
        }

        // Otherwise, get from the user
        if ($user) {
            if ($user->role === 'landlord') {
                return $user->id;
            }
            if (isset($user->landlord_id)) {
                return $user->landlord_id;
            }
        }

        return null;
    }

    /**
     * Get fields to exclude from audit (can be overridden in model).
     */
    public function getAuditExclude(): array
    {
        return property_exists($this, 'auditExclude') ? $this->auditExclude : [];
    }

    /**
     * Get additional metadata for the audit log (can be overridden in model).
     */
    public function getAuditMetadata(): ?array
    {
        return null;
    }

    /**
     * Get audit logs for this model instance.
     */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Manually log a custom audit event.
     */
    public function logCustomAudit(string $eventType, ?array $metadata = null): AuditLog
    {
        $user = Auth::user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'landlord_id' => static::getLandlordId($this, $user),
            'event_type' => $eventType,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->getKey(),
            'old_values' => null,
            'new_values' => null,
            'changed_fields' => null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log a status change event.
     */
    public function logStatusChange(string $oldStatus, string $newStatus, ?string $reason = null): AuditLog
    {
        return $this->logCustomAudit(AuditLog::EVENT_STATUS_CHANGED, [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
        ]);
    }

    /**
     * Log an access event (for sensitive data viewing).
     */
    public function logAccess(?string $context = null): AuditLog
    {
        return $this->logCustomAudit(AuditLog::EVENT_ACCESSED, [
            'context' => $context,
        ]);
    }
}
