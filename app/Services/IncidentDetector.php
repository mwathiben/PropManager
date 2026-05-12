<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\SuspiciousActivityDetected;
use App\Models\SecurityIncident;
use App\Models\SecurityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase-13 BREACH-2: detect breach-likely patterns and turn each
 * detection into a SecurityIncident. Before this service, the only
 * path to a SecurityIncident was a manual call to
 * KenyaDpaService::initiateBreachNotification. Patterns that an
 * operator would investigate as a potential breach — repeated failed
 * logins, oversized data exports, role escalations without
 * invitation, runaway webhook signature failures — produced nothing
 * automated.
 *
 * The seed rule set is intentionally small. Exhaustive detection is
 * BREACH-5 follow-up (Phase 3). Each rule:
 *   - reads its threshold from config (so we can tune without code change)
 *   - reads recent history from security_logs
 *   - if the threshold is crossed, creates a SecurityIncident
 *   - debounces by checking for an existing matching incident in the
 *     last N minutes (the rule's own debounce window)
 *   - dispatches SuspiciousActivityDetected so BREACH-5 listeners
 *     can pick it up
 *
 * Thresholds live in config('security.detection.*'). Each public
 * checkXxx() method returns the SecurityIncident it created, or null
 * when the threshold was not crossed (or a debounce hit).
 */
class IncidentDetector
{
    /**
     * Rule 1: 50+ failed logins for one account in 1h.
     *
     * Distinct from the existing per-IP login throttle (SecurityLog::
     * shouldBlockIp) because the throttle blocks a single IP whereas
     * this rule catches credential-stuffing distributed across many
     * IPs targeting one account.
     */
    public function checkFailedLoginBurst(string $email): ?SecurityIncident
    {
        $threshold = (int) config('security.detection.failed_login_burst.threshold', 50);
        $windowMinutes = (int) config('security.detection.failed_login_burst.window_minutes', 60);
        $debounceMinutes = (int) config('security.detection.failed_login_burst.debounce_minutes', 60);

        $count = SecurityLog::query()
            ->where('event_type', SecurityLog::EVENT_LOGIN_FAILED)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->where(function ($q) use ($email) {
                $q->whereJsonContains('metadata->email', $email)
                    ->orWhere('description', 'like', '%'.$email.'%');
            })
            ->count();

        if ($count < $threshold) {
            return null;
        }

        if ($this->recentlyDetected('failed_login_burst', ['email' => $email], $debounceMinutes)) {
            return null;
        }

        return $this->createIncident(
            rule: 'failed_login_burst',
            severity: SecurityIncident::SEVERITY_HIGH,
            description: "Credential-stuffing pattern: {$count} failed login attempts for {$email} in the last {$windowMinutes}m",
            affectedDataTypes: ['credentials'],
            estimatedAffectedUsers: 1,
            mitigation: 'Account-lockout review + targeted password reset for affected account',
            context: ['email' => $email, 'failed_count' => $count, 'window_minutes' => $windowMinutes],
        );
    }

    /**
     * Rule 2: data-export exceeds threshold rows in one request. Called
     * directly from the export controller after row count is known.
     */
    public function checkLargeDataExport(int $userId, int $rowCount, string $exportType): ?SecurityIncident
    {
        $threshold = (int) config('security.detection.large_export.threshold', 10000);
        $debounceMinutes = (int) config('security.detection.large_export.debounce_minutes', 60);

        if ($rowCount < $threshold) {
            return null;
        }

        if ($this->recentlyDetected('large_export', ['user_id' => $userId], $debounceMinutes)) {
            return null;
        }

        return $this->createIncident(
            rule: 'large_export',
            severity: SecurityIncident::SEVERITY_MEDIUM,
            description: "Large data export: user {$userId} exported {$rowCount} {$exportType} rows in one request",
            affectedDataTypes: ['exported_dataset'],
            estimatedAffectedUsers: $rowCount,
            mitigation: 'Verify export was authorised; review actor session + recent activity',
            context: ['user_id' => $userId, 'row_count' => $rowCount, 'export_type' => $exportType],
        );
    }

    /**
     * Rule 3: role escalation to landlord without a matching invitation
     * record. Called from the user-role-change observer (BREACH-5).
     */
    public function checkUnauthorisedRoleEscalation(int $userId, string $oldRole, string $newRole, bool $invitationExists): ?SecurityIncident
    {
        if ($newRole !== 'landlord' || $oldRole === 'landlord') {
            return null;
        }
        if ($invitationExists) {
            return null;
        }

        // No debounce — role escalation without invitation is rare and
        // every occurrence deserves its own incident.

        return $this->createIncident(
            rule: 'role_escalation_no_invitation',
            severity: SecurityIncident::SEVERITY_HIGH,
            description: "User {$userId} promoted to landlord from {$oldRole} with no LandlordInvitation record",
            affectedDataTypes: ['authorization'],
            estimatedAffectedUsers: 1,
            mitigation: 'Verify escalation actor + revert if unauthorised; rotate sessions for affected landlord',
            context: ['user_id' => $userId, 'old_role' => $oldRole, 'new_role' => $newRole],
        );
    }

    /**
     * Rule 5 (BREACH-5): impersonation frequency. Phase-13 BREACH-5
     * adds this on top of the four rules shipped in BREACH-2 — the
     * admin impersonation feature is high-blast-radius (the admin can
     * read anything the target can), so an abnormal burst is worth
     * an incident even when each individual call passes throttle.
     */
    public function checkImpersonationFrequency(int $adminUserId): ?SecurityIncident
    {
        $threshold = (int) config('security.detection.impersonation.threshold', 5);
        $windowMinutes = (int) config('security.detection.impersonation.window_minutes', 60);
        $debounceMinutes = (int) config('security.detection.impersonation.debounce_minutes', 60);

        $count = SecurityLog::query()
            ->where('event_type', SecurityLog::EVENT_IMPERSONATION_START)
            ->where('user_id', $adminUserId)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->count();

        if ($count <= $threshold) {
            return null;
        }

        if ($this->recentlyDetected('impersonation_frequency', ['admin_id' => $adminUserId], $debounceMinutes)) {
            return null;
        }

        return $this->createIncident(
            rule: 'impersonation_frequency',
            severity: SecurityIncident::SEVERITY_MEDIUM,
            description: "Admin {$adminUserId} initiated {$count} impersonations in {$windowMinutes}m",
            affectedDataTypes: ['authorization'],
            estimatedAffectedUsers: $count,
            mitigation: 'Verify admin session is not compromised; review impersonation targets for sensitivity',
            context: ['admin_user_id' => $adminUserId, 'count' => $count, 'window_minutes' => $windowMinutes],
        );
    }

    /**
     * Rule 4: webhook signature failures > N from one IP in W minutes.
     * Called from the webhook controllers (BREACH-5) after a signature
     * mismatch is logged to security_logs.
     */
    public function checkWebhookSignatureFlood(string $ipAddress): ?SecurityIncident
    {
        $threshold = (int) config('security.detection.webhook_signature.threshold', 10);
        $windowMinutes = (int) config('security.detection.webhook_signature.window_minutes', 1);
        $debounceMinutes = (int) config('security.detection.webhook_signature.debounce_minutes', 30);

        $count = SecurityLog::query()
            ->where('ip_address', $ipAddress)
            ->where('event_type', 'webhook_signature_failed')
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->count();

        if ($count <= $threshold) {
            return null;
        }

        if ($this->recentlyDetected('webhook_signature_flood', ['ip_address' => $ipAddress], $debounceMinutes)) {
            return null;
        }

        return $this->createIncident(
            rule: 'webhook_signature_flood',
            severity: SecurityIncident::SEVERITY_MEDIUM,
            description: "Webhook signature flood: {$count} failures from {$ipAddress} in {$windowMinutes}m",
            affectedDataTypes: ['webhook_endpoint'],
            estimatedAffectedUsers: 0,
            mitigation: 'Add IP to webhook deny-list temporarily; investigate intent (replay attack vs. misconfigured integration)',
            context: ['ip_address' => $ipAddress, 'failure_count' => $count, 'window_minutes' => $windowMinutes],
        );
    }

    /**
     * Has an incident with this rule tag been created in the last
     * $minutes? Prevents one credential-stuffing burst from generating
     * an incident per failed-login event. Context-key debounce is
     * intentionally omitted — the rule + window pair is conservative
     * enough that one detection per window is the right granularity.
     *
     * @param  array<string, mixed>  $contextMatch  reserved for future per-target debounce; not yet applied
     */
    protected function recentlyDetected(string $rule, array $contextMatch, int $minutes): bool
    {
        unset($contextMatch); // reserved (see above)

        return SecurityIncident::query()
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->whereJsonContains('compliance_references', $this->ruleTag($rule))
            ->exists();
    }

    protected function ruleTag(string $rule): string
    {
        return 'phase13_breach2:'.$rule;
    }

    /**
     * Create the SecurityIncident + dispatch the event. compliance_
     * references carries the rule tag so debounce can find prior
     * incidents from this rule.
     */
    protected function createIncident(
        string $rule,
        string $severity,
        string $description,
        array $affectedDataTypes,
        int $estimatedAffectedUsers,
        string $mitigation,
        array $context,
    ): SecurityIncident {
        $incident = DB::transaction(function () use ($rule, $severity, $description, $affectedDataTypes, $estimatedAffectedUsers, $mitigation) {
            return SecurityIncident::create([
                'type' => SecurityIncident::TYPE_DATA_BREACH,
                'severity' => $severity,
                'description' => $description,
                'affected_data_types' => $affectedDataTypes,
                'estimated_affected_users' => $estimatedAffectedUsers,
                'mitigation_measures' => $mitigation,
                'reported_by' => null,
                'reported_at' => now(),
                'notification_deadline' => now()->addHours(72),
                // Phase-13 BREACH-7: 30-day post-incident review deadline.
                'review_due_at' => now()->addDays(30),
                'status' => SecurityIncident::STATUS_REPORTED,
                'compliance_references' => [
                    'kenya_dpa_section_43',
                    'gdpr_article_33',
                    $this->ruleTag($rule),
                ],
            ]);
        });

        Log::channel(config('security.logging.channel', 'security'))->critical(
            'IncidentDetector created SecurityIncident',
            [
                'incident_id' => $incident->id,
                'rule' => $rule,
                'severity' => $severity,
                'context' => $context,
            ]
        );

        SuspiciousActivityDetected::dispatch($incident, $rule, $context);

        return $incident;
    }
}
