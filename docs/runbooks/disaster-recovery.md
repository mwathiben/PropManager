# Disaster Recovery Runbook

This runbook covers PropManager's posture for total or partial
production data loss. Phase-12 ships the automation; this document is
the operator's contract for using it under pressure.

## Targets (RPO / RTO)

| Number | Today | Target by EOY 2026 |
|--------|-------|---------------------|
| **RPO** (acceptable data loss) | 24h | 1h via MySQL binlog-based PITR |
| **RTO** (time to recover) | 4h (cold restore + verification) | 1h (warm replica failover) |

The current targets reflect the daily-cadence `backup:run` schedule
shipped in Phase-12 Phase-1. Tighter RPO requires point-in-time
recovery via binlog streaming to a separate region — out of scope for
Phase 12; planned for a follow-up infrastructure phase.

## Backup architecture

```
production MySQL  ──┐
storage/app uploads ┼─► spatie/laravel-backup ─► encrypted .zip ─► configured disk(s)
                    ┘                                              (default: local; swap to s3
                                                                    in production)
```

- **Schedule** (`routes/console.php`):
  - `backup:run`     daily @ 01:30
  - `backup:clean`   daily @ 01:00 (GFS retention)
  - `backup:monitor` daily @ 06:30 (age + size health checks)
  - `backup:verify`  weekly @ 06:35 Sun (cheap archive-integrity check)
- **Retention** (`config/backup.php`):
  - 14 days of all-backups
  - 12 weeks of weekly snapshots
  - 24 months of monthly snapshots
  - 7 years of yearly snapshots (matches `DATA_RETENTION_YEARS`)
  - 20 GB soft cap (oldest deleted past that)
- **Encryption**: archive password from `BACKUP_ARCHIVE_PASSWORD` env.
  AES-256 default. Lose this password = backups are unreadable;
  escrow it alongside APP_KEY (see `key-rotation.md`).

## Routine verification

`backup:verify` runs weekly and asserts:
1. The newest archive exists on the configured disk.
2. The archive opens as a Zip without errors.
3. A `.sql` dump file is inside, larger than 100 bytes.
4. The dump's first 1KB matches a mysqldump signature.

`backup:monitor` runs daily and asserts age and total size are within
the bounds in `config/backup.php`'s `monitor_backups` section. Failure
on either escalates via the Sentry breadcrumb (OBS-1) and the
operator email recipient configured in `BACKUP_NOTIFY_EMAIL`.

**These checks are necessary but not sufficient.** A backup that's
syntactically valid but missing 90% of the rows still passes both
verify and monitor. The full safeguard is the quarterly drill below.

## Quarterly restore drill (mandatory)

Cadence: first Monday of every quarter. Owner: rotating from on-call.

### Procedure

```bash
# 1. Pick the newest backup
ls -lh storage/app/<APP_NAME>/ | tail -3

# 2. Decrypt + extract
unzip -P "$BACKUP_ARCHIVE_PASSWORD" backup-2026-05-12-01-30-00.zip -d /tmp/dr-drill

# 3. Spin up a throwaway MySQL container (or use a staging DB)
docker run -d --name dr-drill-mysql \
  -e MYSQL_ROOT_PASSWORD=drill \
  -e MYSQL_DATABASE=drill \
  -p 33307:3306 \
  mysql:8.0

# Wait for it to be up...

# 4. Import the dump
docker exec -i dr-drill-mysql mysql -uroot -pdrill drill \
  < /tmp/dr-drill/db-dumps/mysql/propmanager-database.sql

# 5. Run smoke-test queries
docker exec dr-drill-mysql mysql -uroot -pdrill drill -e "
  SELECT COUNT(*) AS users FROM users;
  SELECT COUNT(*) AS landlords FROM users WHERE role='landlord';
  SELECT COUNT(*) AS leases FROM leases;
  SELECT COUNT(*) AS invoices FROM invoices;
  SELECT COUNT(*) AS payments FROM payments;
  SELECT MAX(created_at) AS latest_payment FROM payments;
"

# 6. Tear down
docker rm -f dr-drill-mysql
rm -rf /tmp/dr-drill

# 7. Record the drill in docs/runbooks/dr-drill-log.md
```

A drill is **successful** when:
- Step 4 completes without error.
- Step 5 row counts are within 5% of production's same-day counts.
- `latest_payment` is within RPO of the drill start time.

A drill is **failed** when any of the above is not met. A failed drill
is a P0 incident — treat as if production data IS lost.

## Restore procedure (live incident)

If production has actually lost data:

1. **Page the on-call rotation** via the alerting system. DR drills
   are practice; this is the real thing.
2. **Stop writes** to production: take the app into maintenance mode
   (`php artisan down`) so no new traffic adds inconsistent state.
3. **Identify the target backup**:
   - For total loss: newest available archive.
   - For partial corruption: the archive immediately before the
     known-bad event time.
4. **Verify the archive** before any restore: run `backup:verify
   --disk=...`. Any failure means escalate to the previous archive.
5. **Restore in a SEPARATE database**, not over production. Mistakes
   during restore are common and overwriting production is permanent.
6. **Smoke-test the restored DB** with the queries from the drill
   procedure.
7. **Swap connections**: update production `.env`'s DB_DATABASE /
   DB_HOST to point at the restored DB. `php artisan config:cache &&
   queue:restart`.
8. **Bring production back up**: `php artisan up`.
9. **Post-incident**: file a writeup within 24h covering observed
   RPO/RTO, root cause, and changes needed before the next incident.

## Redis durability (Phase-12 BACKUP-7)

`.env.production.example` proposes `QUEUE_CONNECTION=redis` and
`CACHE_STORE=redis`. On Redis failover or instance loss:

- **In-flight queue jobs are lost.** Critical jobs (payment
  webhooks, notification dispatches) re-enter at the next retry
  via idempotency keys, but uncommitted state is gone.
- **Sessions are dropped.** Every active user is logged out.
- **Cache is empty.** First requests pay the cold-cache penalty
  (rebuilt from DB).

**Mitigation contract** (operators must satisfy at least one):

1. **AWS ElastiCache for Redis** with Multi-AZ + automatic
   failover + automatic backup retention 7 days. Failover RTO
   is sub-30-seconds for Redis 6+.
2. **Self-managed Redis** with AOF persistence (`appendonly yes`)
   plus a hot replica (`replicaof <primary> <port>`), backed up
   to S3 daily via a sidecar `BGSAVE + aws s3 cp` cron.

Either option satisfies a Redis-loss RPO ≤ 24h. Tight RPO (sub-1h)
requires option 1.

**Job persistence supplement**: critical job classes
(`SendNotificationJob`, payment processors) implement
`ShouldBeUnique` so duplicate dispatches during failover collapse to
idempotent re-runs. New job classes MUST follow the same pattern;
the audit Phase-12 RETAIN-7 ensures the dead-letter table is the
authoritative record of any job that ran out of retries.

## Drill log

See `docs/runbooks/dr-drill-log.md` (Phase 12 Phase 4) for the
quarterly drill history. Empty drill log = no DR posture, regardless
of how good the automation looks.
