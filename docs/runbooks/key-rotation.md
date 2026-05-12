# Key Rotation Runbook

This runbook covers the two secret-rotation procedures PropManager
operators need:

1. **APP_KEY rotation** (`php artisan crypt:rotate`) — re-encrypts
   every Laravel-encrypted column when the platform-wide application
   key changes.
2. **Per-landlord bank webhook secret rotation** (`php artisan
   webhook:rotate-secret`) — replaces a single landlord's Coop /
   Equity / KCB webhook secret without disturbing any other tenant.

Both procedures write a `SecurityLog` row for forensic traceability.

---

## 1. APP_KEY rotation

Phase-4 CRYPTO-6 ships a re-encryption command. APP_KEY rotation
touches 7+ column families: User `national_id` / bank details,
`PaymentConfiguration` credentials, `Setting.value`,
`NotificationProviderConfig.credentials`, 2FA secrets / recovery
codes, the Phase-5-cleanup-1 per-landlord webhook secrets, and the
Phase-10B webhook payload archives.

### When to rotate

- **Suspected leak**: APP_KEY appeared in a log file, error trace,
  pastebin, deploy artifact, or a departing ops staff member's
  laptop. Rotate immediately.
- **Scheduled annual**: at minimum every 12 months. Mark the date
  in the team calendar; the next rotation is due **12 months after
  this runbook is followed**.
- **Ops staff offboarding**: when anyone with production env access
  leaves. Rotate within 7 days of their last day.

### Pre-flight

1. **Maintenance window**: announce a 15-minute degraded window. The
   command serialises a chunked DB rewrite; reads / writes during the
   window may temporarily fail to decrypt rows mid-rotation.
2. **Backup**: take a full database backup AND a snapshot of the
   current `.env` (with the existing `APP_KEY`).
3. **Capacity check**: confirm the host has 2× the largest encrypted
   table in free disk space (re-encrypted rows are written before old
   rows are removed in the same transaction).

### Procedure

```bash
# 1. Capture the current key
OLD_KEY=$(grep '^APP_KEY=' .env | cut -d= -f2-)

# 2. Generate a new key WITHOUT applying it yet
NEW_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"

# 3. Stage both keys in .env
sed -i.bak "s|^APP_KEY=.*|APP_KEY=${NEW_KEY}|" .env
echo "APP_KEY_OLD=${OLD_KEY}" >> .env

# 4. Dry run — prints column counts, no writes
php artisan crypt:rotate --dry-run

# 5. Real run
php artisan crypt:rotate --confirm

# 6. Clean up — the old key is no longer needed
sed -i.bak '/^APP_KEY_OLD=/d' .env
rm .env.bak
```

### Post-flight verification

```bash
# Sample a few encrypted columns; decryption should succeed under
# the new key.
php artisan tinker --execute='
  $u = App\Models\User::whereNotNull("national_id")->first();
  echo $u ? ($u->national_id ? "ok: User\\n" : "EMPTY") : "no rows";
  $pc = App\Models\PaymentConfiguration::first();
  echo $pc ? ($pc->paystack_secret_key ? "ok: PaymentConfiguration\\n" : "EMPTY") : "no rows";
'
```

If anything decrypts to empty, restore from the backup taken in step 2
and contact the on-call engineer before retrying.

### Schedule reminder

A `Schedule::call` cron annual reminder lives in
`routes/console.php` — verify it is active and routes to the on-call
distribution list.

### APP_KEY escrow (Phase-12 BACKUP-6)

Backups taken BEFORE an APP_KEY rotation contain rows whose encrypted
columns were encrypted with the OLD key. If those backups need to be
restored AFTER rotation, the old key MUST still be available — once
discarded, the encrypted columns in older backups are permanently
unreadable.

**Escrow procedure** (run BEFORE rotation):

1. Generate the new key but do not deploy it yet.
2. Write the OLD key + the rotation date to a sealed secret store:
   - **Recommended**: 1Password vault entry named
     `propmanager/app-key/<YYYY-MM-DD>` with the base64 key in the
     password field.
   - **Alternative**: AWS Secrets Manager entry with KMS-encrypted
     value, same naming convention.
3. The escrow entry MUST live as long as the oldest backup that
   needs it — at minimum, until `keep_yearly_backups_for_years`
   (currently 7) has elapsed since the rotation date.
4. Document the escrow location in the rotation's audit_log
   metadata (already captured by `crypt:rotate` — see
   `escrow_location` in the SecurityLog row).

**Unsealing procedure** (during a restore from a pre-rotation backup):

1. Restore the backup to a throwaway DB schema.
2. Retrieve the escrowed key from the vault.
3. In the throwaway environment's `.env`, set `APP_KEY` to the
   escrowed key.
4. Run `php artisan crypt:rotate --old-key=base64:... --confirm` to
   migrate the restored rows to the current production key.
5. Now the restored DB can be merged into production safely.

---

## 2. Per-landlord webhook secret rotation

Phase-5 CRYPTO-11 stores Coop / Equity / KCB webhook secrets
per-landlord. A leak of one landlord's secret should not require
rotating the platform-wide env fallback.

### When to rotate

- A landlord reports a stolen device with their webhook secret on it.
- A bank-side incident exposes the shared secret.
- An ops staff member sets up a new bank integration and needs the
  initial secret — use this command rather than `tinker`.

### Procedure

```bash
# 1. Dry run — confirm the landlord + bank + old-hash you're rotating
php artisan webhook:rotate-secret --bank=coop --landlord=42

# 2. Real rotation. The new secret prints to stdout ONCE.
ROTATED_BY=ops@propmanager.test \
  php artisan webhook:rotate-secret \
    --bank=coop \
    --landlord=42 \
    --confirm \
    --reason="Suspected leak in customer ticket #INC-1234"

# 3. Relay the new plaintext secret to the bank's webhook
#    administration UI / API within 24 hours. The bank will continue
#    signing callbacks with the OLD secret until they re-key on their
#    side; BankWebhookController falls back to the env-wide secret
#    during the cutover.
```

### Post-flight

```bash
# Verify the SecurityLog row exists
php artisan tinker --execute='
  $log = App\Models\SecurityLog::where("event_type", "webhook_secret_rotated")
    ->where("landlord_id", 42)
    ->latest()
    ->first();
  echo $log ? "ok: " . $log->metadata["new_secret_hash"] . "\\n" : "MISSING";
'
```

---

## Auditing rotations

All rotations (both APP_KEY and per-landlord) write `SecurityLog`
rows. Query history:

```sql
SELECT created_at, event_type, landlord_id, metadata
FROM security_logs
WHERE event_type IN ('app_key_rotated', 'webhook_secret_rotated')
ORDER BY created_at DESC
LIMIT 50;
```

The `metadata` column captures `old_secret_hash` / `new_secret_hash`
(sha256) so an incident response can answer "was this specific secret
ever in use, and if so, between when and when?"
