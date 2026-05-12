<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\BreachEscalationAlert;
use App\Models\SecurityIncident;
use App\Models\SecurityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Phase-13 BREACH-3: hourly 72-hour SLA enforcement. Without this,
 * a breach can be recorded today and the regulator-notification clock
 * runs out silently — Section 43 / Article 33 violation that becomes
 * invisible until a regulator audit. This command surfaces overdue
 * and imminent-deadline incidents to ops + writes a HIGH-severity
 * SecurityLog row each time it escalates one.
 *
 * Stages:
 *   IMMINENT — within 12h of the deadline, not yet notified
 *   OVERDUE  — past the deadline, not yet notified
 *
 * Idempotency: the SecurityLog row stamps the most recent escalation,
 * so re-running won't double-page; the BreachEscalationAlert mailable
 * may still queue another email per run, which is intentional — we
 * want repeated paging on overdue items until ops acknowledges via
 * the mark-regulator-notified path.
 */
class BreachEscalateOverdue extends Command
{
    protected $signature = 'breach:escalate-overdue
        {--imminent-hours=12 : Hours before deadline to start paging}';

    protected $description = 'Escalate SecurityIncidents whose 72h ODPC notification window is closing or has passed.';

    public function handle(): int
    {
        $imminentHours = max(1, (int) $this->option('imminent-hours'));
        $imminentBoundary = now()->addHours($imminentHours);
        $recipient = config('security.kenya_dpa.breach_notification_email');

        $incidents = SecurityIncident::query()
            ->pendingOdpcNotification()
            ->where('notification_deadline', '<=', $imminentBoundary)
            ->orderBy('notification_deadline')
            ->get();

        if ($incidents->isEmpty()) {
            $this->info('No incidents past the imminent boundary.');

            return self::SUCCESS;
        }

        foreach ($incidents as $incident) {
            $stage = $incident->notification_deadline?->isPast() ? 'overdue' : 'imminent';

            SecurityLog::create([
                'user_id' => null,
                'landlord_id' => null,
                'event_type' => 'breach_sla_'.$stage,
                'severity' => SecurityLog::SEVERITY_CRITICAL,
                'description' => "Breach incident #{$incident->id} regulator-notification {$stage}",
                'metadata' => [
                    'incident_id' => $incident->id,
                    'severity' => $incident->severity,
                    'reported_at' => $incident->reported_at?->toIso8601String(),
                    'notification_deadline' => $incident->notification_deadline?->toIso8601String(),
                    'estimated_affected_users' => $incident->estimated_affected_users,
                    'stage' => $stage,
                ],
                'is_suspicious' => true,
            ]);

            Log::channel(config('security.logging.channel', 'security'))->critical(
                "Breach SLA {$stage}",
                [
                    'incident_id' => $incident->id,
                    'notification_deadline' => $incident->notification_deadline?->toIso8601String(),
                    'recipient_configured' => $recipient !== null,
                ]
            );

            if ($recipient) {
                Mail::to($recipient)->queue(new BreachEscalationAlert($incident, $stage));
            }

            $this->warn(sprintf(
                '[#%d] %s (deadline %s, severity=%s)',
                $incident->id,
                strtoupper($stage),
                $incident->notification_deadline?->toDateTimeString() ?? 'unset',
                $incident->severity,
            ));
        }

        if (! $recipient) {
            $this->warn('KENYA_DPA_BREACH_EMAIL is not configured — escalations logged but not emailed.');
        }

        $this->info('Escalations processed: '.$incidents->count());

        return self::SUCCESS;
    }
}
