<?php

namespace App\Services;

use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SecurityLogger
{
    protected ?Request $request;

    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? request();
    }

    /**
     * Log a security event.
     */
    public function log(
        string $eventType,
        ?string $description = null,
        array $metadata = [],
        string $severity = SecurityLog::SEVERITY_INFO,
        ?User $user = null,
        bool $isSuspicious = false
    ): ?SecurityLog {
        if (! config('security.logging.enabled', true)) {
            return null;
        }

        $user = $user ?? Auth::user();

        try {
            $log = SecurityLog::create([
                'user_id' => $user?->id,
                'landlord_id' => $this->getLandlordId($user),
                'event_type' => $eventType,
                'severity' => $severity,
                'ip_address' => $this->getIpAddress(),
                'user_agent' => $this->request?->userAgent(),
                'url' => $this->request?->fullUrl(),
                'method' => $this->request?->method(),
                'description' => $description,
                'metadata' => $metadata,
                'session_id' => session()->getId(),
                'is_suspicious' => $isSuspicious,
            ]);

            // Also log to file for redundancy
            $this->logToFile($eventType, $description, $metadata, $severity, $user);

            // Check for suspicious patterns
            if ($this->detectSuspiciousActivity($eventType)) {
                $this->handleSuspiciousActivity($log);
            }

            return $log;
        } catch (\Exception $e) {
            Log::error('Failed to create security log', [
                'error' => $e->getMessage(),
                'event_type' => $eventType,
            ]);

            return null;
        }
    }

    /**
     * Log a successful login.
     */
    public function logLogin(User $user): ?SecurityLog
    {
        return $this->log(
            SecurityLog::EVENT_LOGIN,
            "User {$user->email} logged in successfully",
            ['email' => $user->email],
            SecurityLog::SEVERITY_INFO,
            $user
        );
    }

    /**
     * Log a failed login attempt.
     */
    public function logFailedLogin(string $email, ?string $reason = null): ?SecurityLog
    {
        $isSuspicious = SecurityLog::shouldBlockIp($this->getIpAddress());

        // SCOPE-P5: failed-login events fire pre-auth, so TenantScope's
        // creating hook (Auth::check()-gated) won't populate landlord_id.
        // Resolving the target user lets the affected landlord see the
        // failed-login attempt in their own audit-log review instead of
        // it being visible only to super admins.
        $targetUser = User::where('email', $email)->first();

        return $this->log(
            SecurityLog::EVENT_LOGIN_FAILED,
            "Failed login attempt for {$email}".($reason ? ": {$reason}" : ''),
            ['email' => $email, 'reason' => $reason],
            SecurityLog::SEVERITY_WARNING,
            $targetUser,
            $isSuspicious
        );
    }

    /**
     * Log a logout.
     */
    public function logLogout(User $user): ?SecurityLog
    {
        return $this->log(
            SecurityLog::EVENT_LOGOUT,
            "User {$user->email} logged out",
            ['email' => $user->email],
            SecurityLog::SEVERITY_INFO,
            $user
        );
    }

    /**
     * Log a password change.
     */
    public function logPasswordChange(User $user): ?SecurityLog
    {
        return $this->log(
            SecurityLog::EVENT_PASSWORD_CHANGE,
            "Password changed for {$user->email}",
            ['email' => $user->email],
            SecurityLog::SEVERITY_INFO,
            $user
        );
    }

    /**
     * Log a password reset request.
     */
    public function logPasswordResetRequest(string $email): ?SecurityLog
    {
        return $this->log(
            SecurityLog::EVENT_PASSWORD_RESET_REQUEST,
            "Password reset requested for {$email}",
            ['email' => $email],
            SecurityLog::SEVERITY_INFO
        );
    }

    /**
     * Log a password reset completion.
     */
    public function logPasswordReset(User $user): ?SecurityLog
    {
        return $this->log(
            SecurityLog::EVENT_PASSWORD_RESET,
            "Password reset completed for {$user->email}",
            ['email' => $user->email],
            SecurityLog::SEVERITY_INFO,
            $user
        );
    }

    /**
     * Log a role change.
     */
    public function logRoleChange(User $user, string $oldRole, string $newRole, ?User $changedBy = null): ?SecurityLog
    {
        $changedBy = $changedBy ?? Auth::user();

        return $this->log(
            SecurityLog::EVENT_ROLE_CHANGE,
            "Role changed for {$user->email} from {$oldRole} to {$newRole}",
            [
                'user_id' => $user->id,
                'email' => $user->email,
                'old_role' => $oldRole,
                'new_role' => $newRole,
                'changed_by' => $changedBy?->email,
            ],
            SecurityLog::SEVERITY_WARNING,
            $changedBy
        );
    }

    /**
     * Log 2FA enabled.
     */
    public function logTwoFactorEnabled(User $user): ?SecurityLog
    {
        return $this->log(
            SecurityLog::EVENT_TWO_FACTOR_ENABLED,
            "Two-factor authentication enabled for {$user->email}",
            ['email' => $user->email],
            SecurityLog::SEVERITY_INFO,
            $user
        );
    }

    /**
     * Log 2FA disabled.
     */
    public function logTwoFactorDisabled(User $user): ?SecurityLog
    {
        return $this->log(
            SecurityLog::EVENT_TWO_FACTOR_DISABLED,
            "Two-factor authentication disabled for {$user->email}",
            ['email' => $user->email],
            SecurityLog::SEVERITY_WARNING,
            $user
        );
    }

    /**
     * Log failed 2FA attempt.
     */
    public function logTwoFactorFailed(User $user): ?SecurityLog
    {
        return $this->log(
            SecurityLog::EVENT_TWO_FACTOR_FAILED,
            "Failed 2FA verification for {$user->email}",
            ['email' => $user->email],
            SecurityLog::SEVERITY_WARNING,
            $user
        );
    }

    /**
     * Log impersonation start.
     */
    public function logImpersonationStart(User $admin, User $target): ?SecurityLog
    {
        return $this->log(
            SecurityLog::EVENT_IMPERSONATION_START,
            "{$admin->email} started impersonating {$target->email}",
            [
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
                'target_id' => $target->id,
                'target_email' => $target->email,
            ],
            SecurityLog::SEVERITY_WARNING,
            $admin
        );
    }

    /**
     * Log impersonation end.
     */
    public function logImpersonationEnd(User $admin, User $target): ?SecurityLog
    {
        return $this->log(
            SecurityLog::EVENT_IMPERSONATION_END,
            "{$admin->email} stopped impersonating {$target->email}",
            [
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
                'target_id' => $target->id,
                'target_email' => $target->email,
            ],
            SecurityLog::SEVERITY_INFO,
            $admin
        );
    }

    /**
     * Log data export.
     */
    public function logDataExport(User $user, string $exportType, array $details = []): ?SecurityLog
    {
        return $this->log(
            SecurityLog::EVENT_DATA_EXPORT,
            "Data exported by {$user->email}: {$exportType}",
            array_merge(['export_type' => $exportType], $details),
            SecurityLog::SEVERITY_INFO,
            $user
        );
    }

    /**
     * Log data deletion.
     */
    public function logDataDelete(User $user, string $dataType, array $details = []): ?SecurityLog
    {
        return $this->log(
            SecurityLog::EVENT_DATA_DELETE,
            "Data deleted by {$user->email}: {$dataType}",
            array_merge(['data_type' => $dataType], $details),
            SecurityLog::SEVERITY_WARNING,
            $user
        );
    }

    /**
     * Log sensitive data access.
     */
    public function logSensitiveDataAccess(User $user, string $dataType, array $details = []): ?SecurityLog
    {
        return $this->log(
            SecurityLog::EVENT_SENSITIVE_DATA_ACCESS,
            "{$user->email} accessed sensitive data: {$dataType}",
            array_merge(['data_type' => $dataType], $details),
            SecurityLog::SEVERITY_INFO,
            $user
        );
    }

    /**
     * Log account locked.
     */
    public function logAccountLocked(string $email, string $reason): ?SecurityLog
    {
        return $this->log(
            SecurityLog::EVENT_ACCOUNT_LOCKED,
            "Account locked for {$email}: {$reason}",
            ['email' => $email, 'reason' => $reason],
            SecurityLog::SEVERITY_CRITICAL,
            null,
            true
        );
    }

    /**
     * Log suspicious activity.
     */
    public function logSuspiciousActivity(string $description, array $details = [], ?User $user = null): ?SecurityLog
    {
        return $this->log(
            SecurityLog::EVENT_SUSPICIOUS_ACTIVITY,
            $description,
            $details,
            SecurityLog::SEVERITY_CRITICAL,
            $user,
            true
        );
    }

    /**
     * Get the client IP address.
     */
    protected function getIpAddress(): ?string
    {
        if (! $this->request) {
            return null;
        }

        // Check for proxied IP addresses
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if ($ip = $this->request->server($header)) {
                // Handle comma-separated IPs (X-Forwarded-For)
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $this->request->ip();
    }

    /**
     * Get the landlord ID for the log entry.
     */
    protected function getLandlordId(?User $user): ?int
    {
        if (! $user) {
            return null;
        }

        if ($user->role === 'landlord') {
            return $user->id;
        }

        return $user->landlord_id;
    }

    /**
     * Log to file for redundancy.
     */
    protected function logToFile(
        string $eventType,
        ?string $description,
        array $metadata,
        string $severity,
        ?User $user
    ): void {
        $channel = config('security.logging.channel', 'security');

        $context = [
            'event_type' => $eventType,
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'ip_address' => $this->getIpAddress(),
            'metadata' => $metadata,
        ];

        match ($severity) {
            SecurityLog::SEVERITY_CRITICAL => Log::channel($channel)->critical($description, $context),
            SecurityLog::SEVERITY_ERROR => Log::channel($channel)->error($description, $context),
            SecurityLog::SEVERITY_WARNING => Log::channel($channel)->warning($description, $context),
            default => Log::channel($channel)->info($description, $context),
        };
    }

    /**
     * Detect suspicious activity patterns.
     */
    protected function detectSuspiciousActivity(string $eventType): bool
    {
        if (! config('security.intrusion_detection.enabled', true)) {
            return false;
        }

        $ip = $this->getIpAddress();

        if (! $ip) {
            return false;
        }

        // Check for too many failed login attempts
        if ($eventType === SecurityLog::EVENT_LOGIN_FAILED) {
            return SecurityLog::shouldBlockIp($ip);
        }

        return false;
    }

    /**
     * Handle detected suspicious activity.
     */
    protected function handleSuspiciousActivity(SecurityLog $log): void
    {
        // Mark as suspicious
        $log->update(['is_suspicious' => true]);

        // Alert admins if configured
        if (config('security.intrusion_detection.alert_admins', true)) {
            // This can be expanded to send notifications to admins
            Log::channel(config('security.logging.channel', 'security'))
                ->critical('Suspicious activity detected', [
                    'log_id' => $log->id,
                    'event_type' => $log->event_type,
                    'ip_address' => $log->ip_address,
                ]);
        }
    }
}
