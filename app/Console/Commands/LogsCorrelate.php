<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\BankWebhookLog;
use App\Models\SecurityLog;
use App\Models\WebhookLog;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Phase-14 OBSERV-10: emit a unified, time-sorted view of every log
 * row matching a request_id (or user_id, or IP) across the three
 * audit-trail tables. Before this command, "show me everything that
 * happened from request X" was a manual SQL UNION across audit_logs,
 * security_logs, and webhook_logs — each table stored the request_id
 * in metadata in a slightly different shape.
 *
 * Usage:
 *   php artisan logs:correlate --request-id=<uuid>
 *   php artisan logs:correlate --user-id=42
 *   php artisan logs:correlate --ip=203.0.113.5 --since=24h
 *   php artisan logs:correlate --request-id=<uuid> --json
 */
class LogsCorrelate extends Command
{
    protected $signature = 'logs:correlate
        {--request-id= : Match logs whose metadata->request_id equals this value}
        {--user-id= : Match logs by user_id}
        {--ip= : Match logs by ip_address}
        {--since=24h : Limit lookback (1h, 24h, 7d, 30d)}
        {--json : Emit JSON instead of a table}';

    protected $description = 'Correlate audit + security + webhook log rows by request_id / user_id / ip.';

    public function handle(): int
    {
        $since = $this->parseSince((string) $this->option('since'));
        $rows = collect()
            ->concat($this->fromAuditLogs($since))
            ->concat($this->fromSecurityLogs($since))
            ->concat($this->fromWebhookLogs($since))
            ->concat($this->fromBankWebhookLogs($since))
            ->sortBy('at');

        if ($rows->isEmpty()) {
            $this->warn('No matching rows.');

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line($rows->values()->toJson(JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->table(
            ['Time', 'Source', 'Event', 'Actor', 'Detail'],
            $rows->map(fn ($r) => [
                $r['at'],
                $r['source'],
                $r['event'],
                $r['actor'] ?? '—',
                substr((string) ($r['detail'] ?? ''), 0, 80),
            ])->all()
        );

        return self::SUCCESS;
    }

    private function fromAuditLogs(\DateTimeInterface $since): Collection
    {
        $query = AuditLog::query()->where('created_at', '>=', $since);
        $requestId = (string) ($this->option('request-id') ?? '');
        $userId = $this->option('user-id');
        $ip = (string) ($this->option('ip') ?? '');

        if ($requestId !== '') {
            $query->whereJsonContains('metadata->request_id', $requestId);
        }
        if ($userId !== null && $userId !== '') {
            $query->where('user_id', (int) $userId);
        }
        if ($ip !== '') {
            $query->where('ip_address', $ip);
        }

        return $query->limit(500)->get()->map(fn ($a) => [
            'at' => (string) $a->created_at,
            'source' => 'audit_logs',
            'event' => (string) $a->event_type,
            'actor' => $a->user_id ? '#'.$a->user_id : null,
            'detail' => $a->auditable_type.'#'.$a->auditable_id,
        ]);
    }

    private function fromSecurityLogs(\DateTimeInterface $since): Collection
    {
        $query = SecurityLog::query()->where('created_at', '>=', $since);
        $requestId = (string) ($this->option('request-id') ?? '');
        $userId = $this->option('user-id');
        $ip = (string) ($this->option('ip') ?? '');

        if ($requestId !== '') {
            $query->whereJsonContains('metadata->request_id', $requestId);
        }
        if ($userId !== null && $userId !== '') {
            $query->where('user_id', (int) $userId);
        }
        if ($ip !== '') {
            $query->where('ip_address', $ip);
        }

        return $query->limit(500)->get()->map(fn ($s) => [
            'at' => (string) $s->created_at,
            'source' => 'security_logs',
            'event' => (string) $s->event_type,
            'actor' => $s->user_id ? '#'.$s->user_id : null,
            'detail' => (string) $s->description,
        ]);
    }

    private function fromWebhookLogs(\DateTimeInterface $since): Collection
    {
        if (! class_exists(WebhookLog::class)) {
            return collect();
        }

        $query = WebhookLog::withoutGlobalScope('landlord')->where('created_at', '>=', $since);
        $requestId = (string) ($this->option('request-id') ?? '');
        $ip = (string) ($this->option('ip') ?? '');

        // Phase-21 DEFER-OBSERV-1: closes the comment-tagged follow-up.
        // webhook_logs.request_id is now stamped by
        // WebhookLogService::recordHit so the correlate query path is
        // symmetric with audit_logs/security_logs.
        if ($requestId !== '') {
            $query->where('request_id', $requestId);
        }
        if ($ip !== '') {
            $query->where('ip_address', $ip);
        }

        return $query->limit(500)->get()->map(fn ($w) => [
            'at' => (string) $w->created_at,
            'source' => 'webhook_logs',
            'event' => 'webhook_'.($w->provider ?? 'unknown'),
            'actor' => $w->ip_address,
            'detail' => substr((string) ($w->event_type ?? ''), 0, 60),
        ]);
    }

    /**
     * Phase-21 DEFER-OBSERV-1: bank webhook ingress (KCB / Coop / Equity
     * via BankWebhookController) writes to bank_webhook_logs with
     * request_id stamped from the X-Request-Id header. Surfaces here
     * alongside the other audit-trail tables.
     */
    private function fromBankWebhookLogs(\DateTimeInterface $since): Collection
    {
        if (! class_exists(BankWebhookLog::class)) {
            return collect();
        }

        $query = BankWebhookLog::query()->where('created_at', '>=', $since);
        $requestId = (string) ($this->option('request-id') ?? '');
        $ip = (string) ($this->option('ip') ?? '');

        if ($requestId !== '') {
            $query->where('request_id', $requestId);
        }
        if ($ip !== '') {
            $query->where('ip_address', $ip);
        }

        return $query->limit(500)->get()->map(fn ($b) => [
            'at' => (string) $b->created_at,
            'source' => 'bank_webhook_logs',
            'event' => 'bank_'.($b->bank_code ?? 'unknown'),
            'actor' => $b->ip_address,
            'detail' => substr((string) ($b->event_type ?? $b->status ?? ''), 0, 60),
        ]);
    }

    private function parseSince(string $window): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable;
        if (preg_match('/^(\d+)([hd])$/', $window, $m)) {
            $n = (int) $m[1];
            $unit = $m[2] === 'h' ? 'hours' : 'days';

            return $now->modify("-{$n} {$unit}");
        }

        return $now->modify('-24 hours');
    }
}
