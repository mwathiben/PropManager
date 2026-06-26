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

        // Phase-28 first-response breach.
        $responseRows = Ticket::query()
            ->withoutGlobalScope('landlord')
            ->breachedSla()
            ->get();

        // Phase-49 TICKETS-SLA-DEEP-2: resolution-stage breach.
        $resolutionRows = Ticket::query()
            ->withoutGlobalScope('landlord')
            ->breachedResolutionSla()
            ->get();

        $this->emitPriorityGauges($metrics, $responseRows->groupBy('priority'), $resolutionRows->groupBy('priority'));

        $fired = 0;
        if (! $dryRun) {
            $fired += $this->fireResponseBreachEvents($responseRows, $now);
            $fired += $this->fireResolutionBreachEvents($resolutionRows, $now);
        }

        $this->info("tickets:audit-sla: {$responseRows->count()} response + {$resolutionRows->count()} resolution breach(es), {$fired} notification(s) fired".($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }

    /**
     * Emit Prometheus gauges for response and resolution breach counts per priority.
     */
    private function emitPriorityGauges(MetricsService $metrics, \Illuminate\Support\Collection $responseByPriority, \Illuminate\Support\Collection $resolutionByPriority): void
    {
        foreach (array_keys(Ticket::SLA_SECONDS) as $priority) {
            $responseCount = $responseByPriority->get($priority, collect())->count();
            $resolutionCount = $resolutionByPriority->get($priority, collect())->count();
            try {
                $metrics->gauge('ticket_sla_breach_count', (float) $responseCount, ['priority' => $priority]);
                $metrics->gauge('ticket_resolution_breach_count', (float) $resolutionCount, ['priority' => $priority]);
            } catch (\Throwable $e) {
                Log::warning('tickets:audit-sla gauge emit failed', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Fire TicketSlaBreached events for first-response breaches, idempotent within 24h.
     */
    private function fireResponseBreachEvents(\Illuminate\Support\Collection $responseRows, CarbonImmutable $now): int
    {
        $fired = 0;
        foreach ($responseRows as $ticket) {
            $key = sprintf('ticket:sla_breach:%d:response:%s', $ticket->id, $ticket->sla_due_at?->timestamp ?? 'null');
            if (Cache::add($key, true, now()->addDay())) {
                TicketSlaBreached::dispatch($ticket, $now, TicketSlaBreached::TYPE_RESPONSE);
                $fired++;
            }
        }

        return $fired;
    }

    /**
     * Fire TicketSlaBreached events for resolution breaches, idempotent within 24h.
     */
    private function fireResolutionBreachEvents(\Illuminate\Support\Collection $resolutionRows, CarbonImmutable $now): int
    {
        $fired = 0;
        foreach ($resolutionRows as $ticket) {
            $key = sprintf('ticket:sla_breach:%d:resolution:%s', $ticket->id, $ticket->resolution_due_at?->timestamp ?? 'null');
            if (Cache::add($key, true, now()->addDay())) {
                TicketSlaBreached::dispatch($ticket, $now, TicketSlaBreached::TYPE_RESOLUTION);
                $fired++;
            }
        }

        return $fired;
    }
}
