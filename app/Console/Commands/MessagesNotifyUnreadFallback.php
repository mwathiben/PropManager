<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use App\Services\MetricsService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase-63 INBOX-NOTIFY-3: digest cron — catches the trailing case
 * the per-event listener misses (user was active when message
 * arrived, then walked away without replying or reading).
 *
 * Cadence: every 15 minutes. Idempotent per (thread_id, user_id) for
 * 60 minutes via Cache::add so a slow-replying user isn't paged on
 * every cron tick.
 */
class MessagesNotifyUnreadFallback extends Command
{
    protected $signature = 'messages:notify-unread-fallback {--dry-run : Report only, no notifications sent}';

    protected $description = 'Phase-63: dispatch fallback notifications for inbox messages unread > 15 minutes.';

    public function __construct(
        private readonly NotificationService $notifications,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now = now();
        $stale = $now->copy()->subMinutes(15);
        $cutoff = $now->copy()->subHours(24);

        $candidates = DB::table('message_thread_participants as p')
            ->join('message_threads as t', 't.id', '=', 'p.thread_id')
            ->whereNull('t.deleted_at')
            ->whereNotNull('t.last_message_at')
            ->where('t.last_message_at', '<=', $stale)
            ->where('t.last_message_at', '>=', $cutoff)
            ->where(function ($q) {
                $q->whereNull('p.last_read_at')
                    ->orWhereColumn('p.last_read_at', '<', 't.last_message_at');
            })
            ->select([
                'p.thread_id',
                'p.user_id',
                't.landlord_id',
                't.last_message_at',
            ])
            ->get();

        $dispatched = 0;

        foreach ($candidates as $row) {
            $idemKey = 'inbox:digest:'.$row->thread_id.':'.$row->user_id;
            if (! Cache::add($idemKey, 1, 3600)) {
                continue;
            }

            $latest = DB::table('messages')
                ->where('thread_id', $row->thread_id)
                ->where('sender_id', '!=', $row->user_id)
                ->whereNull('deleted_at')
                ->orderByDesc('created_at')
                ->select(['id', 'sender_id', 'body'])
                ->first();

            if ($latest === null) {
                continue;
            }

            if ($dryRun) {
                $this->info("Would dispatch fallback to user {$row->user_id} on thread {$row->thread_id}");
                $dispatched++;

                continue;
            }

            $sender = User::withoutGlobalScope('landlord')->find($latest->sender_id);
            $senderName = $sender?->name ?? __('inbox.notification.sender_unknown');

            $this->notifications->send(
                recipientId: (int) $row->user_id,
                type: Notification::TYPE_NEW_MESSAGE,
                subject: __('inbox.notification.subject', ['sender' => $senderName]),
                message: Str::limit($latest->body, 120),
                data: [
                    'thread_id' => $row->thread_id,
                    'message_id' => $latest->id,
                    'sender_name' => $senderName,
                ],
                landlordId: (int) $row->landlord_id,
            );

            $dispatched++;
        }

        app(MetricsService::class)->gauge('inbox_unread_fallback_count', $dispatched);

        $this->info("Phase-63 unread-fallback digest: {$dispatched} dispatched.");

        return self::SUCCESS;
    }
}
