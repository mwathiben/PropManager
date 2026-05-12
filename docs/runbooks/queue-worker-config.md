# Queue Worker Configuration (Phase-16 QUEUE-8 + QUEUE-10)

## Canonical worker invocation

```bash
php artisan queue:work \
    --queue=payments,notifications,default,bulk \
    --memory=256 \
    --timeout=600 \
    --tries=3 \
    --max-jobs=1000 \
    --max-time=3600 \
    --sleep=3 \
    --backoff=10
```

| Flag | Rationale |
|------|-----------|
| `--queue=payments,notifications,default,bulk` | Phase-16 QUEUE-10 priority order. Workers drain `payments` first, then `notifications`, then `default`, then `bulk`. A 1000-job bulk fan-out cannot starve a payment-received confirmation that needs to land in seconds |
| `--memory=256` | OOM the worker before PHP's per-process memory ceiling. Combined with `--max-jobs` the worker recycles regularly so leaks don't accumulate |
| `--timeout=600` | Outer worker timeout. **Must be ≥ the longest job-level `$timeout` (currently 600 on SendBulkNotificationsJob)** otherwise the worker SIGTERMs before the job can self-clean |
| `--tries=3` | Default retry count if the job didn't declare `$tries`. Job-level `$tries` overrides |
| `--max-jobs=1000` | Recycle after 1k jobs. Cheap insurance against slow memory leaks in dependencies (PDF generation in particular) |
| `--max-time=3600` | Or recycle after 1h, whichever hits first. Pairs with `--max-jobs` |
| `--sleep=3` | Idle poll interval when no jobs |
| `--backoff=10` | Re-poll backoff after a worker-level exception (not job retry backoff — the job's `$backoff` controls that) |

## Queue priority convention (Phase-16 QUEUE-10)

| Queue | What goes here | SLA |
|-------|----------------|-----|
| `payments` | Payment-received confirmations, webhook follow-ups, refunds | < 5s (synchronous-feeling) |
| `notifications` | Single-recipient SMS / WhatsApp / Push | < 30s |
| `default` | Everything else (the existing default) | < 60s |
| `bulk` | `SendBulkNotificationsJob` + `PerRecipientBulkNotificationJob` + `WarmFinanceCacheJob` + `ArchiveOldPayments` + `ExportUserData` | Best effort |

Each ShouldQueue class can opt into a specific queue via `public string $queue = 'payments';`. Without this declaration the job lands on `default`.

## supervisord

```ini
; /etc/supervisor/conf.d/propmanager-worker.conf
[program:propmanager-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/propmanager/artisan queue:work --queue=payments,notifications,default,bulk --memory=256 --timeout=600 --tries=3 --max-jobs=1000 --max-time=3600 --sleep=3
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/propmanager-worker.log
stopwaitsecs=620
```

Notes:
- `stopwaitsecs=620` is `--timeout (600) + safety margin`. supervisord sends SIGTERM, waits, then SIGKILL. The margin lets the worker finish its in-flight job
- `numprocs=4` is a starting point; size against `failed_jobs_total{age_bucket=last_hour}` growth + per-queue `queue_depth{queue=X}` Prometheus gauges (Phase-16 QUEUE-6)
- `autorestart=true` recycles after `--max-jobs` / `--max-time` triggers an exit-with-code-0

## systemd

```ini
# /etc/systemd/system/propmanager-worker@.service
[Unit]
Description=PropManager queue worker (instance %i)
After=network.target redis.service mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/propmanager
ExecStart=/usr/bin/php artisan queue:work --queue=payments,notifications,default,bulk --memory=256 --timeout=600 --tries=3 --max-jobs=1000 --max-time=3600 --sleep=3
Restart=on-failure
RestartSec=5
TimeoutStopSec=620
StandardOutput=append:/var/log/propmanager-worker-%i.log
StandardError=append:/var/log/propmanager-worker-%i.log

[Install]
WantedBy=multi-user.target
```

Run multiple instances with `sudo systemctl enable --now propmanager-worker@{1,2,3,4}.service`.

## Restart cadence

```bash
# After every deploy
php artisan queue:restart
```

This signals running workers to exit gracefully at their next poll. supervisord / systemd then respawn them with the new code. **Always required after a code deploy** — workers preload code on boot and won't pick up changes.

The Phase-11 `scripts/deploy.sh` calls `queue:restart` at the end of every deploy.

## Sizing

Use the Phase-16 QUEUE-6 gauges:

- `queue_depth{queue=payments}` — if consistently > 0 during business hours, payment workers are under-provisioned
- `queue_depth{queue=bulk}` — expected to spike during bulk sends; should drain in O(minutes), not O(hours)
- `failed_jobs_total{age_bucket=last_hour}` — should be < 5 in steady state; spikes mean upstream outage (see queue-triage.md)

## Test mode

`config('queue.default') === 'sync'` in CI/tests runs jobs synchronously in-process — there's no worker. This is intentional: tests should not depend on a queue worker, and `Bus::fake()` / `Queue::fake()` are the canonical test patterns.

## See also

- `docs/runbooks/queue-triage.md` — what to do when failed_jobs grows
- `docs/runbooks/slo.md` — SLO definitions per queue
- `docs/runbooks/alert-thresholds.md` — alert thresholds for queue depth + failed jobs
