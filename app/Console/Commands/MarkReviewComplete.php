<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SecurityIncident;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Phase-13 BREACH-7 companion: ops acknowledgement that the 30-day
 * post-incident review has been filed. Sets review_completed_at,
 * which stops the BreachReviewOverdue surfacing for this incident.
 */
class MarkReviewComplete extends Command
{
    protected $signature = 'dpa:mark-review-complete
        {--incident= : SecurityIncident id (integer, required)}
        {--notes= : Optional review notes pointer (URL or wiki ref)}
        {--confirm : Required to actually mark complete}';

    protected $description = 'Acknowledge that the 30-day post-incident review report for an incident has been filed.';

    public function handle(): int
    {
        $incidentId = (int) ($this->option('incident') ?? 0);
        if ($incidentId <= 0) {
            $this->error('--incident is required (positive integer SecurityIncident id).');

            return self::INVALID;
        }

        $incident = SecurityIncident::find($incidentId);
        if (! $incident) {
            $this->error("SecurityIncident id={$incidentId} not found.");

            return self::FAILURE;
        }

        if ($incident->review_completed_at) {
            $this->warn("Incident #{$incidentId} already marked review-complete at {$incident->review_completed_at}.");

            return self::SUCCESS;
        }

        if (! $this->option('confirm')) {
            $this->printDryRunSummary($incidentId, $incident);

            return self::SUCCESS;
        }

        $this->applyReviewComplete($incident, $incidentId);

        $this->info("Incident #{$incidentId} review marked complete.");

        return self::SUCCESS;
    }

    private function printDryRunSummary(int $incidentId, SecurityIncident $incident): void
    {
        $this->warn('DRY RUN — pass --confirm to apply.');
        $this->line("Incident:  #{$incidentId}");
        $this->line('Due at:    '.$incident->review_due_at?->toDateTimeString());
    }

    private function applyReviewComplete(SecurityIncident $incident, int $incidentId): void
    {
        $incident->update(['review_completed_at' => now()]);

        $actor = $this->resolveActor();
        $notes = trim((string) ($this->option('notes') ?? ''));

        SecurityLog::create([
            'user_id' => $actor?->id,
            'landlord_id' => null,
            'event_type' => 'breach_review_complete',
            'severity' => SecurityLog::SEVERITY_INFO,
            'description' => "Post-incident review filed for #{$incidentId}",
            'metadata' => $this->buildAuditMetadata($incidentId, $incident, $actor, $notes),
            'is_suspicious' => false,
        ]);
    }

    private function buildAuditMetadata(int $incidentId, SecurityIncident $incident, ?User $actor, string $notes): array
    {
        return [
            'incident_id' => $incidentId,
            'notes' => $notes !== '' ? $notes : null,
            'acknowledged_by' => $actor?->email ?? 'cli',
            'days_late' => $incident->review_due_at
                ? (int) max(0, now()->diffInDays($incident->review_due_at, false) * -1)
                : null,
        ];
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
