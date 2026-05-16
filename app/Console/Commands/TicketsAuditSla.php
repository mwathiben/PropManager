<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\TicketSlaBreached;
use App\Models\Ticket;
use App\Services\MetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Phase-28 TENANT-MAINT-1: nightly SLA breach detector. Counts open
 * tickets whose sla_due_at < now() with NULL first_response_at, emits
 * Prometheus gauge ticket_sla_breach_count{priority=...}, and fires
 * TicketSlaBreached for each breached row (idempotent within 24h via
 * Cache lock keyed on ticket_id + sla_due_at).
 */
class TicketsAuditSla extends Command
{
    protected $signature = 'tickets:audit-sla {--dry-run}';

    protected $description = 'Phase-28 TENANT-MAINT-1: detect and notify tickets that breached SLA.';

    public function handle(MetricsService $metrics): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now = CarbonImmutable::now();

        $rows = Ticket::query()
            ->withoutGlobalScope('landlord')
            ->breachedSla()
            ->get();

        $byPriority = $rows->groupBy('priority');

        foreach (array_keys(Ticket::SLA_SECONDS) as $priority) {
            $count = $byPriority->get($priority, collect())->count();
            try {
                $metrics->gauge(
                    'ticket_sla_breach_count',
                    (float) $count,
                    ['priority' => $priority],
                );
            } catch (\Throwable $e) {
                Log::warning('tickets:audit-sla gauge emit failed', ['error' => $e->getMessage()]);
            }
        }

        $fired = 0;
        if (! $dryRun) {
            foreach ($rows as $ticket) {
                $key = sprintf('ticket:sla_breach:%d:%s', $ticket->id, $ticket->sla_due_at?->timestamp ?? 'null');
                if (Cache::add($key, true, now()->addDay())) {
                    TicketSlaBreached::dispatch($ticket, $now);
                    $fired++;
                }
            }
        }

        $this->info("tickets:audit-sla: {$rows->count()} breach(es), {$fired} notification(s) fired".($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}
