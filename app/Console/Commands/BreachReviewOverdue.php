<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SecurityIncident;
use App\Models\SecurityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Phase-13 BREACH-7: weekly check for SecurityIncidents whose 30-day
 * post-incident review deadline has passed without
 * review_completed_at being set. Without this command an operator
 * can ship the regulator notification, close the ticket, and never
 * file the follow-up — a Section 43 follow-up gap.
 *
 * Output is a single email + SecurityLog row listing overdue
 * incidents. Acknowledgement via dpa:mark-review-complete.
 */
class BreachReviewOverdue extends Command
{
    protected $signature = 'breach:review-overdue';

    protected $description = 'Surface SecurityIncidents whose 30-day post-incident review is overdue.';

    public function handle(): int
    {
        $recipient = config('security.kenya_dpa.breach_notification_email');

        $incidents = SecurityIncident::query()
            ->whereNotNull('review_due_at')
            ->whereNull('review_completed_at')
            ->where('review_due_at', '<', now())
            ->orderBy('review_due_at')
            ->get();

        if ($incidents->isEmpty()) {
            $this->info('No overdue post-incident reviews.');

            return self::SUCCESS;
        }

        SecurityLog::create([
            'user_id' => null,
            'landlord_id' => null,
            'event_type' => 'breach_review_overdue_batch',
            'severity' => SecurityLog::SEVERITY_WARNING,
            'description' => "{$incidents->count()} overdue post-incident reviews",
            'metadata' => [
                'incident_ids' => $incidents->pluck('id')->all(),
                'oldest_review_due_at' => $incidents->first()->review_due_at?->toIso8601String(),
            ],
            'is_suspicious' => false,
        ]);

        Log::channel(config('security.logging.channel', 'security'))->warning(
            'Overdue post-incident reviews',
            ['count' => $incidents->count()]
        );

        foreach ($incidents as $incident) {
            $this->line(sprintf(
                '[#%d] review_due_at=%s, severity=%s',
                $incident->id,
                $incident->review_due_at?->toDateTimeString() ?? 'unset',
                $incident->severity,
            ));
        }

        if ($recipient) {
            $body = "Overdue post-incident reviews ({$incidents->count()}):\n\n";
            foreach ($incidents as $i) {
                $body .= "- #{$i->id}: due {$i->review_due_at?->toDateTimeString()}, severity={$i->severity}\n";
            }

            Mail::raw($body, function ($m) use ($recipient) {
                $m->to($recipient)
                    ->subject('[BREACH-REVIEW-OVERDUE] post-incident reports outstanding');
            });
        } else {
            $this->warn('KENYA_DPA_BREACH_EMAIL is not configured — review-overdue notice logged but not emailed.');
        }

        return self::SUCCESS;
    }
}
