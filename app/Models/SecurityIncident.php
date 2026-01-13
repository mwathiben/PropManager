<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityIncident extends Model
{
    /**
     * Incident types.
     */
    public const TYPE_DATA_BREACH = 'data_breach';

    public const TYPE_UNAUTHORIZED_ACCESS = 'unauthorized_access';

    public const TYPE_MALWARE = 'malware';

    public const TYPE_PHISHING = 'phishing';

    public const TYPE_DOS_ATTACK = 'dos_attack';

    public const TYPE_OTHER = 'other';

    /**
     * Severity levels.
     */
    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    /**
     * Incident statuses.
     */
    public const STATUS_REPORTED = 'reported';

    public const STATUS_INVESTIGATING = 'investigating';

    public const STATUS_CONTAINED = 'contained';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'type',
        'severity',
        'description',
        'affected_data_types',
        'estimated_affected_users',
        'mitigation_measures',
        'reported_by',
        'reported_at',
        'notification_deadline',
        'odpc_notified_at',
        'users_notified_at',
        'resolved_at',
        'status',
        'resolution_notes',
        'compliance_references',
    ];

    protected $casts = [
        'affected_data_types' => 'array',
        'compliance_references' => 'array',
        'reported_at' => 'datetime',
        'notification_deadline' => 'datetime',
        'odpc_notified_at' => 'datetime',
        'users_notified_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the user who reported this incident.
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * Check if ODPC notification is overdue.
     */
    public function isOdpcNotificationOverdue(): bool
    {
        if ($this->odpc_notified_at) {
            return false;
        }

        return now()->isAfter($this->notification_deadline);
    }

    /**
     * Get hours remaining until notification deadline.
     */
    public function getHoursUntilDeadlineAttribute(): int
    {
        if ($this->odpc_notified_at) {
            return 0;
        }

        return max(0, now()->diffInHours($this->notification_deadline, false));
    }

    /**
     * Mark ODPC as notified.
     */
    public function markOdpcNotified(): void
    {
        $this->update([
            'odpc_notified_at' => now(),
            'status' => self::STATUS_INVESTIGATING,
        ]);
    }

    /**
     * Mark affected users as notified.
     */
    public function markUsersNotified(): void
    {
        $this->update(['users_notified_at' => now()]);
    }

    /**
     * Resolve the incident.
     */
    public function resolve(string $resolutionNotes): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolution_notes' => $resolutionNotes,
        ]);
    }

    /**
     * Scope: Unresolved incidents.
     */
    public function scopeUnresolved($query)
    {
        return $query->whereNotIn('status', [self::STATUS_RESOLVED, self::STATUS_CLOSED]);
    }

    /**
     * Scope: By severity.
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope: Pending ODPC notification.
     */
    public function scopePendingOdpcNotification($query)
    {
        return $query->whereNull('odpc_notified_at')
            ->where('type', self::TYPE_DATA_BREACH);
    }
}
