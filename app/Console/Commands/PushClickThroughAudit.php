<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Notification;
use App\Services\MetricsService;
use Illuminate\Console\Command;

class PushClickThroughAudit extends Command
{
    protected $signature = 'push:click-through-audit';

    protected $description = 'Phase-39 VENDOR-OBSERV-1: emit push_click_through_rate_24h gauge from notifications channel=push sent in the last 24h.';

    public function handle(MetricsService $metrics): int
    {
        $since = now()->subDay();

        $sent = Notification::query()
            ->withoutGlobalScopes()
            ->where('channel', 'push')
            ->where('status', 'sent')
            ->where('sent_at', '>=', $since)
            ->count();

        $clicked = Notification::query()
            ->withoutGlobalScopes()
            ->where('channel', 'push')
            ->where('status', 'sent')
            ->where('sent_at', '>=', $since)
            ->whereNotNull('read_at')
            ->count();

        $rate = $sent > 0 ? round($clicked / $sent, 4) : 0.0;

        $metrics->gauge('push_click_through_rate_24h', $rate);
        $metrics->gauge('push_notifications_sent_24h', $sent);
        $metrics->gauge('push_notifications_clicked_24h', $clicked);

        $this->info(sprintf(
            'push click-through 24h: %d sent, %d clicked, rate=%s',
            $sent,
            $clicked,
            $rate,
        ));

        return self::SUCCESS;
    }
}
