<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityLog extends Model
{
    use HasFactory, TenantScope;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'landlord_id',
        'event_type',
        'severity',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'description',
        'metadata',
        'session_id',
        'country',
        'city',
        'is_suspicious',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'metadata' => 'array',
        'is_suspicious' => 'boolean',
    ];

    /**
     * Event types for security logging.
     */
    public const EVENT_LOGIN = 'login';

    public const EVENT_LOGOUT = 'logout';

    public const EVENT_LOGIN_FAILED = 'login_failed';

    public const EVENT_PASSWORD_CHANGE = 'password_change';

    public const EVENT_PASSWORD_RESET = 'password_reset';

    public const EVENT_PASSWORD_RESET_REQUEST = 'password_reset_request';

    public const EVENT_ROLE_CHANGE = 'role_change';

    public const EVENT_DATA_EXPORT = 'data_export';

    public const EVENT_DATA_DELETE = 'data_delete';

    public const EVENT_TWO_FACTOR_ENABLED = 'two_factor_enabled';

    public const EVENT_TWO_FACTOR_DISABLED = 'two_factor_disabled';

    public const EVENT_TWO_FACTOR_FAILED = 'two_factor_failed';

    public const EVENT_IMPERSONATION_START = 'impersonation_start';

    public const EVENT_IMPERSONATION_END = 'impersonation_end';

    public const EVENT_SENSITIVE_DATA_ACCESS = 'sensitive_data_access';

    public const EVENT_ACCOUNT_LOCKED = 'account_locked';

    public const EVENT_ACCOUNT_UNLOCKED = 'account_unlocked';

    public const EVENT_SUSPICIOUS_ACTIVITY = 'suspicious_activity';

    public const EVENT_EMAIL_CHANGE = 'email_change';

    public const EVENT_PROFILE_UPDATE = 'profile_update';

    /**
     * Severity levels.
     */
    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_ERROR = 'error';

    public const SEVERITY_CRITICAL = 'critical';

    /**
     * Get the user associated with this log entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the landlord associated with this log entry.
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Scope a query to only include logs of a specific event type.
     */
    public function scopeOfType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope a query to only include logs of a specific severity.
     */
    public function scopeOfSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope a query to only include suspicious activity.
     */
    public function scopeSuspicious($query)
    {
        return $query->where('is_suspicious', true);
    }

    /**
     * Scope a query to only include logs from a specific IP.
     */
    public function scopeFromIp($query, string $ip)
    {
        return $query->where('ip_address', $ip);
    }

    /**
     * Scope a query to only include logs from a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include logs from a date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include recent logs.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Get failed login attempts for an IP in the last X minutes.
     */
    public static function getFailedLoginAttempts(string $ip, int $minutes = 15): int
    {
        return static::where('ip_address', $ip)
            ->where('event_type', self::EVENT_LOGIN_FAILED)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();
    }

    /**
     * Check if an IP should be blocked due to too many failed attempts.
     */
    public static function shouldBlockIp(string $ip, ?int $threshold = null, int $minutes = 15): bool
    {
        $threshold = $threshold ?? config('security.intrusion_detection.failed_login_threshold', 5);

        return static::getFailedLoginAttempts($ip, $minutes) >= $threshold;
    }
}
