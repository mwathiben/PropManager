<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\OnboardingResumeMailable;
use App\Models\OnboardingSession;
use App\Services\MetricsService;
use App\Services\Onboarding\OnboardingResumeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Phase-46 PROGRESS-RESUME-2/3: nightly sweep for stalled onboarding
 * sessions. A session that's been idle for 3+ days gets a nudge email
 * with a 7-day signed resume link (rate-limited to 1 nudge per 24h);
 * sessions idle for 30+ days are sealed into abandoned_at.
 *
 * Emits onboarding_session_abandoned_count gauge (sessions where
 * last_touched_at < today-14-days but not yet sealed).
 *
 * Cron: daily 09:00 Africa/Nairobi (after the audit-stale crons,
 * during business hours so the user receives the nudge when they're
 * likely to read it).
 */
class NudgeStalledOnboarding extends Command
{
    protected $signature = 'onboarding:nudge-stalled';

    protected $description = 'Phase-46 PROGRESS-RESUME-2/3: nudge stalled onboarding sessions + seal abandoned ones.';

    public function handle(MetricsService $metrics, OnboardingResumeService $resume): int
    {
        $nudgesSent = 0;
        $sealed = 0;
        $abandonedCount = 0;

        // 1. Nudge candidates: last_touched_at < today-3-days AND not
        //    completed AND not abandoned AND no nudge in last 24h.
        $nudgeCandidates = OnboardingSession::query()
            ->whereNull('completed_at')
            ->whereNull('abandoned_at')
            ->where('last_touched_at', '<', now()->subDays(3))
            ->where(function ($q) {
                $q->whereNull('last_nudge_sent_at')
                    ->orWhere('last_nudge_sent_at', '<', now()->subHours(24));
            })
            ->get();

        foreach ($nudgeCandidates as $session) {
            try {
                $url = $resume->generate($session, $session->user_id);
                // Phase-47 MAIL-DISPATCH-2: dispatch the queued Mailable.
                // afterCommit on the Mailable means a transaction rollback
                // in this iteration won't fire orphan emails. We still
                // bump last_nudge_sent_at after queue so the 24h rate
                // limit holds even if mail delivery is lazy.
                $email = optional($session->user)->email;
                if ($email !== null) {
                    Mail::to($email)->queue(new OnboardingResumeMailable($url, $session));
                }
                $session->update(['last_nudge_sent_at' => now()]);
                $nudgesSent++;
            } catch (\Throwable $e) {
                Log::warning('[onboarding:nudge-stalled] failed to nudge', [
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 2. Seal candidates: last_touched_at < today-30-days AND not
        //    completed AND not abandoned.
        OnboardingSession::query()
            ->whereNull('completed_at')
            ->whereNull('abandoned_at')
            ->where('last_touched_at', '<', now()->subDays(30))
            ->each(function (OnboardingSession $session) use (&$sealed): void {
                $session->update([
                    'abandoned_at' => now(),
                    'step_history' => array_merge((array) $session->step_history, [[
                        'step' => $session->current_step,
                        'action' => 'auto_abandoned',
                        'at' => now()->toIso8601String(),
                    ]]),
                ]);
                $sealed++;
            });

        // 3. Abandoned-count gauge for the growth team.
        $abandonedCount = OnboardingSession::query()
            ->whereNotNull('abandoned_at')
            ->where('abandoned_at', '>', now()->subDays(7))
            ->count();
        $metrics->gauge('onboarding_session_abandoned_count', $abandonedCount);

        // Phase-47 MAIL-DISPATCH-2: visibility gauge for ops dashboards.
        $metrics->gauge('onboarding_nudge_mail_sent_count', $nudgesSent);

        $this->info(sprintf(
            'Nudges sent: %d. Sessions sealed: %d. Recently-abandoned count: %d.',
            $nudgesSent,
            $sealed,
            $abandonedCount,
        ));

        return self::SUCCESS;
    }
}
