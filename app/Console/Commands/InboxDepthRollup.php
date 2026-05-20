<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\MessageThread;
use App\Services\MetricsService;
use App\Services\Sre\AlertFiringRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase-67 INBOX-OBSERVABILITY: daily platform-wide snapshot of inbox
 * health. Emits DB-derived gauges so ops can spot a stalled inbox (read
 * ratio collapsing), a messaging spike, or — critically — any attachment
 * the scanner flagged as malware in the last 24h.
 *
 * Real-time counters (inbox_search_queries_count,
 * inbox_attachment_scan_infected_count, inbox_spam_rejected_count,
 * inbox_rate_limit_hits_count) are emitted at the point of action and
 * graphed via Prometheus rate(); this cron complements them with
 * point-in-time gauges that cannot be derived from a monotonic counter.
 *
 * Runs platform-wide. In a console context TenantScope is never
 * registered (it gates on Auth::check()), so the counts already span
 * every landlord; the explicit withoutGlobalScope('landlord') is a
 * defensive guarantee should this ever run inside an authenticated
 * context. The DB::table queries sidestep Document/AuditLog TenantScope
 * by construction.
 */
class InboxDepthRollup extends Command
{
    protected $signature = 'inbox:depth-rollup';

    protected $description = 'Emit daily platform-wide inbox health gauges (threads, read ratio, 24h messages, attachment scans + infections)';

    private const INFECTED_EVENT = 'inbox.attachment.infected';

    public function handle(MetricsService $metrics, AlertFiringRecorder $alerts): int
    {
        $since = now()->subDay();

        $threadsTotal = MessageThread::withoutGlobalScope('landlord')->count();
        $threadsOpen = MessageThread::withoutGlobalScope('landlord')
            ->where('status', MessageThread::STATUS_OPEN)
            ->count();

        $readRatio = $this->readRatio();

        $messages24h = DB::table('messages')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $since)
            ->count();

        $attachmentScans24h = DB::table('documents')
            ->where('documentable_type', Message::class)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $since)
            ->count();

        $attachmentInfected24h = DB::table('audit_logs')
            ->where('event_type', self::INFECTED_EVENT)
            ->where('created_at', '>=', $since)
            ->count();

        $metrics->gauge('inbox_threads_total', (float) $threadsTotal);
        $metrics->gauge('inbox_threads_open', (float) $threadsOpen);
        $metrics->gauge('inbox_read_ratio', $readRatio);
        $metrics->gauge('inbox_messages_24h', (float) $messages24h);
        $metrics->gauge('inbox_attachment_scans_24h', (float) $attachmentScans24h);
        $metrics->gauge('inbox_attachment_infected_24h', (float) $attachmentInfected24h);

        $this->info("threads={$threadsTotal} open={$threadsOpen} read_ratio={$readRatio} messages_24h={$messages24h} scans_24h={$attachmentScans24h} infected_24h={$attachmentInfected24h}");

        if ($attachmentInfected24h > 0) {
            $alerts->record(
                alertKey: 'inbox_attachment_infected',
                value: (float) $attachmentInfected24h,
                threshold: 0.0,
                metadata: ['window' => '24h'],
            );
            $this->warn("{$attachmentInfected24h} infected attachment(s) blocked in the last 24h — see docs/runbooks/inbox.md#attachment-malware-detected");
        } else {
            $alerts->resolve('inbox_attachment_infected');
        }

        return self::SUCCESS;
    }

    /**
     * Fraction of participant inboxes that are fully caught up: their
     * last_read_at is at or past the thread's latest message (an empty
     * thread counts as caught up). Returns 1.0 when there are no
     * participants so an empty platform doesn't read as "0% read".
     */
    private function readRatio(): float
    {
        // Numerator and denominator must cover the same population: a
        // soft-deleted thread's participant rows (the pivot has no
        // soft-delete) must not count as permanently-unread inboxes.
        $base = DB::table('message_thread_participants as p')
            ->join('message_threads as t', 't.id', '=', 'p.thread_id')
            ->whereNull('t.deleted_at');

        $total = (clone $base)->count();

        if ($total === 0) {
            return 1.0;
        }

        $caughtUp = $base
            ->where(function ($query) {
                $query->whereNull('t.last_message_at')
                    ->orWhereColumn('p.last_read_at', '>=', 't.last_message_at');
            })
            ->count();

        return round($caughtUp / $total, 4);
    }
}
