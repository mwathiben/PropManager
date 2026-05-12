# Queue Triage Runbook (Phase-16 QUEUE-5)

When you get a `[ALERT] failed_jobs growth threshold crossed` email from the Phase-5 OBS-13 growth monitor — or you see the Grafana `failed_jobs_total{age_bucket=last_hour}` gauge climbing — use this runbook.

## 1. Survey the failed jobs

```bash
php artisan queue:failed
```

Lists every failed job: ID, timestamp, queue, payload class, and the truncated exception. **Read the timestamps first**: if all failures cluster within a 5-minute window, the upstream had a hard outage and most jobs are safely retryable. If failures span hours, there's a sustained problem.

## 2. Inspect a specific failure

```bash
# Pretty-print one row
php artisan tinker
>>> DB::table('failed_jobs')->where('uuid', 'PASTE-UUID')->first()->exception
```

The full exception trace tells you the root cause. Match it to the failure-mode table below.

## 3. Pick the action

| Symptom in `exception` column | Root cause | Action |
|--------------------------------|------------|--------|
| `ConnectionException`, `cURL error 7`, `cURL error 28` | Upstream API unreachable / timeout | **Retry safe**: `queue:retry {uuid}` — the upstream should be back by now |
| `RequestException ... HTTP 429` | Hit provider rate limit | **Retry after a delay**: wait 5-15 min, then `queue:retry`. Look at the provider's recent send volume to right-size rate limits |
| `RequestException ... HTTP 5xx` | Upstream had an outage | **Retry safe**: `queue:retry {uuid}` |
| `CircuitOpenException` | Phase-16 circuit breaker tripped | **Investigate first**: check `circuit_breaker.opened{provider}` Prometheus counter; once provider is healthy, the breaker auto-closes. `queue:retry` then |
| `QueryException ... Deadlock found` | Transient DB lock contention | **Retry safe**: `queue:retry {uuid}` |
| `QueryException ... duplicate key` | Already-processed event being replayed | **Forget**, do NOT retry: `queue:forget {uuid}`. The idempotency check is working as intended |
| `ProcessTimedOutException` | Job exceeded `$timeout` | **Investigate then retry**: if it's a `GenerateInvoicePdf` job, the invoice may be unusually large. Bump `$timeout` if it's a pattern, then retry |
| `RecipientNotFoundException` | User row was deleted between dispatch + processing | **Forget**: `queue:forget {uuid}`. The notification is no longer deliverable |
| `ChannelSendException` | Notification provider returned a hard error | **Investigate**: Twilio response body usually says why (bad number, opt-out, etc.). Often `queue:forget` is correct — the next dispatch will pick a different channel via fallback |
| `ModelNotFoundException` | The dispatched job referenced a row that doesn't exist | **Forget**: the job is targeting deleted data |

## 4. Idempotency safety table (which jobs are safe to bulk-retry)

| Job class | Bulk-retry safe? | Why |
|-----------|-------------------|-----|
| `SendNotificationJob` | YES | `ShouldBeUniqueUntilProcessing` dedup + Notification.status check |
| `PerRecipientBulkNotificationJob` | YES | Notification.data->batch_id dedup |
| `SendBulkNotificationsJob` | YES | Dispatcher only — filters already-sent recipients |
| `GenerateInvoicePdf` | YES | Skips regeneration if `pdf_path` exists |
| `ProcessQueuedPaymentIntents` | YES | Idempotency keys per intent |
| `ArchiveOldPayments` | YES (but slow) | Looks at `archived_at IS NULL` |
| `ExportUserData` | NO | Could double-send the export email — verify first |
| `FallbackNotificationJob` | NO | Picks the next channel each retry; can double-send via multiple channels |

If a job is NOT in the bulk-retry-safe column, retry one-at-a-time and inspect.

## 5. Bulk operations (use with care)

```bash
# Retry every failed job — DANGEROUS, only if you're sure
php artisan queue:retry all

# Retry every failed job from the last hour
php artisan queue:retry --queue=notifications $(php artisan queue:failed | grep '<recent timestamp>' | awk '{print $1}')

# Forget every failed job older than 7 days
php artisan queue:prune-failed --hours=168
```

The Phase-12 RETAIN-9 schedule already prunes at 720h. The Phase-5 OBS-13 alert fires daily based on the 25-row threshold over 24h. The Phase-16 `queue_depth{queue=X}` + `failed_jobs_total` gauges expose the live-time-series view.

## 6. Find the affected user / entity

Failed jobs use `SerializesModels`. Decode the payload to see which row was targeted:

```bash
php artisan tinker
>>> $job = DB::table('failed_jobs')->where('uuid', 'PASTE-UUID')->first();
>>> $payload = json_decode($job->payload, true);
>>> unserialize($payload['data']['command'])
```

The resulting object has the original constructor arguments — e.g. `SendNotificationJob` has `recipientId`, `notificationId`, `type`, etc. Match against the User / Notification table to find the affected entity.

## 7. After triage

Update `failed_jobs_total{age_bucket=last_hour}` should drop as you process the queue. If it doesn't, jobs are still being dispatched against the broken upstream — the root cause is not yet fixed. Hold off on further retries until the cause is verified addressed.

## See also

- `docs/runbooks/circuit-breaker.md` — when to reset / enable the breaker
- `docs/runbooks/queue-worker-config.md` — supervisord / systemd unit config
- `docs/runbooks/slo.md` — agreed-upon notification + payment SLOs
