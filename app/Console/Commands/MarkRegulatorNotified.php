<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SecurityIncident;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Phase-13 BREACH-3 companion: ops acknowledgement that the ODPC
 * notification was sent. Sets odpc_notified_at, which stops the
 * BreachEscalateOverdue paging loop. The acknowledging actor is
 * recorded via the same ROTATED_BY convention used elsewhere; the
 * SecurityLog entry preserves who acknowledged on behalf of audit.
 */
class MarkRegulatorNotified extends Command
{
    protected $signature = 'dpa:mark-regulator-notified
        {--incident= : SecurityIncident id (integer, required)}
        {--reference= : Free-text regulator reference / ticket number}
        {--confirm : Required to actually update the incident}';

    protected $description = 'Acknowledge that the ODPC has been notified for a given SecurityIncident.';

    public function handle(): int
    {
        $incidentId = (int) ($this->option('incident') ?? 0);
        $reference = trim((string) ($this->option('reference') ?? ''));

        if ($incidentId <= 0) {
            $this->error('--incident is required (positive integer SecurityIncident id).');

            return self::INVALID;
        }

        $incident = SecurityIncident::find($incidentId);
        if (! $incident) {
            $this->error("SecurityIncident id={$incidentId} not found.");

            return self::FAILURE;
        }

        if ($incident->odpc_notified_at) {
            $this->warn("Incident #{$incidentId} already marked notified at {$incident->odpc_notified_at}.");

            return self::SUCCESS;
        }

        if (! $this->option('confirm')) {
            $this->showDryRun($incidentId, $incident, $reference);

            return self::SUCCESS;
        }

        $incident->markOdpcNotified();

        $this->recordAuditLog($incidentId, $incident, $reference);

        $this->info("Incident #{$incidentId} marked as regulator-notified.");

        return self::SUCCESS;
    }

    private function showDryRun(int $incidentId, SecurityIncident $incident, string $reference): void
    {
        $this->warn('DRY RUN — pass --confirm to record the acknowledgement.');
        $this->line("Incident:  #{$incidentId}");
        $this->line('Deadline:  '.$incident->notification_deadline?->toDateTimeString());
        $this->line('Reference: '.($reference !== '' ? $reference : '(none)'));
    }

    private function recordAuditLog(int $incidentId, SecurityIncident $incident, string $reference): void
    {
        $actor = $this->resolveActor();
        SecurityLog::create([
            'user_id' => $actor?->id,
            'landlord_id' => null,
            'event_type' => 'breach_regulator_notified',
            'severity' => SecurityLog::SEVERITY_WARNING,
            'description' => "ODPC notified for incident #{$incidentId}",
            'metadata' => [
                'incident_id' => $incidentId,
                'reference' => $reference !== '' ? $reference : null,
                'acknowledged_by' => $actor?->email ?? 'cli',
                'deadline_met' => ! $incident->notification_deadline?->isPast(),
            ],
            'is_suspicious' => false,
        ]);
    }

    private function resolveActor(): ?User
    {
        $email = env('ROTATED_BY');
        if (! $email) {
            return null;
        }

        return User::where('email', $email)->first();
    }
}
