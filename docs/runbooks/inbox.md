# Communication Inbox

Phase-63 [COMMUNICATION-INBOX] adds bi-directional landlord ↔ tenant message threads on top of the Phase 28 [TENANT-PORTAL] notification surface.

## Schema

| Table | Purpose | Key constraints |
|---|---|---|
| `message_threads` | Thread root. Polymorphic `subject` attaches to a Lease / Ticket / NULL (standalone) | TenantScope (landlord_id), SoftDeletes, Auditable, composite `(landlord_id, status, last_message_at)` for the inbox-list sort |
| `messages` | Individual messages | NO TenantScope — isolation inherits from parent thread via the participants pivot. CASCADE on thread_id, NULL-on-delete on sender_id so historical messages survive GDPR right-to-erasure |
| `message_thread_participants` | Authoritative isolation gate + read-receipt cursor | `unique(thread_id, user_id)`, secondary index `(user_id, thread_id)` for `scopeForUser` joins from the user side |

`MessageThread::scopeForUser($user)` whereHas('participants', user_id = X) is the only authoritative way to query threads visible to a given user — `TenantScope` on landlord_id alone leaks cross-tenant visibility when two tenants share the same landlord. Every controller (landlord + tenant) routes through `forUser()`.

## Thread lifecycle

```
       create
         |
         v
       OPEN ─────────────────┐
        | ^                  |
        | | unlock           | archive
        v |                  v
      LOCKED  ◄── unlock ── ARCHIVED
```

| Transition | Actor | Endpoint | System message |
|---|---|---|---|
| create | landlord / caretaker / tenant | POST `/message-threads` (landlord) or POST `/tenant/inbox` (tenant) | — |
| reply | any participant | POST `/message-threads/{id}/messages` (landlord) or POST `/tenant/inbox/{id}/messages` (tenant) | — |
| archive | landlord only | POST `/message-threads/{id}/archive` | `inbox.thread_archived` |
| lock | landlord only | POST `/message-threads/{id}/lock` | `inbox.message.thread_locked_by_landlord` |
| unlock | landlord only | POST `/message-threads/{id}/unlock` | `inbox.message.thread_unlocked_by_landlord` |
| sender soft-delete (≤ 5min) | sender only | DELETE `/messages/{id}` | `inbox.message.deleted_by_sender` |

`MessagePolicy::create` returns false when the parent thread is not `STATUS_OPEN`, so both `LOCKED` and `ARCHIVED` block new posts. Tenants do not have archive/lock affordances.

## Participant roles

| Role | Created by | Notes |
|---|---|---|
| `landlord` | implicit on landlord-initiated threads + on tenant-initiated threads (their landlord is auto-added) | Always exactly one per thread |
| `caretaker` | landlord adds explicitly via `participants[]` array | Multiple allowed for fan-out (e.g. cross-property emergency) |
| `tenant` | implicit on tenant-initiated threads + when landlord adds via `participants[]` | Form Request gates `Rule::exists` to users with matching `landlord_id` — closes the cross-landlord participant spoofing vector |
| `system` (logical) | `MessageThread::recordSystemEvent($body)` | Encoded as `message_type=system` + `sender_id=NULL`. Immutable — `Message::canBeDeletedBy` returns false |

## Real-time

| Mechanism | Channel | Auth |
|---|---|---|
| Server → participant | `private-inbox.thread.{thread_id}` (Reverb) | Closure in `routes/channels.php` checks `message_thread_participants WHERE thread_id=X AND user_id=current_user` — pivot membership, NOT landlord_id |
| `MessagePosted` event | `broadcast(new MessagePosted($message))->toOthers()` | Sender excluded via Socket-ID |
| Read receipts | PATCH `/messages/{id}/read` | Updates `message_thread_participants.last_read_at` for the calling user only. Idempotent (skips write when stored value ≥ message.created_at) |
| Typing indicators | `Echo.private(...).whisper('typing', { user_id, name })` | Pure client-to-client; never persists, never round-trips through Laravel. `useTypingIndicator` composable wraps the pattern |

`HandleInertiaRequests` shares `auth.inbox_unread_total` (sum across all threads where user is participant, excluding own messages, respecting `last_read_at`). Cached 30s.

## Notification fallback

When a message is posted, two parallel paths fire:

1. **Real-time push** via `MessagePosted` → `inbox.thread.{id}` channel. Online recipients see the message immediately.
2. **`SendUnreadMessageFallback` listener** (ShouldQueue, Phase-16 backoff $tries=4 $backoff=[30,60,300,1800]) fires `NotificationService::send(type=new_message)` ONLY when:
   - `sender_id` is NOT NULL (system messages never page)
   - recipient's `users.last_active_at` is older than 5 minutes (Reverb already delivered)
   - `Cache::add` idempotency lock keyed `inbox:fallback:{message_id}:{user_id}` ttl 10min (listener retry won't re-page)

Routed through `NotificationService::send`, the existing `NotificationPreference` matrix + channel selector + quiet-hours all apply automatically. A user globally opted-out of SMS won't get an inbox SMS even with `new_message_enabled=true`.

**Digest cron** (`messages:notify-unread-fallback`, every 15 min Africa/Nairobi) catches the trailing case: user was active when message arrived, then walked away without replying or reading. Walks `message_thread_participants` for threads where `last_message_at` between 15-min-ago and 24-hours-ago AND (`last_read_at IS NULL` OR `last_read_at < last_message_at`). Per-`(thread_id, user_id)` `Cache::add` idempotency 60-min so a slow-replying user isn't paged on every cron tick. Emits `inbox_unread_fallback_count` gauge.

`users.last_active_at` is touched by `HandleInertiaRequests::share()` on every Inertia request, debounced to one write per 60 seconds via in-place timestamp comparison.

## Retention

| Source | Window | Override |
|---|---|---|
| `config('inbox.retention.default_days', 2557)` (Kenya DPA 7yr) | platform default | env `INBOX_RETENTION_DAYS` |
| `users.message_retention_days` | per-landlord opt-in | NULL = use platform default |

`messages:enforce-retention` daily 03:15 Africa/Nairobi chunks landlords (100/batch), computes effective retention, batch soft-deletes via single UPDATE per landlord, emits `messages_enforce_retention_deleted_count{landlord_id}` gauge.

**Legal-hold override**: dispute / litigation scenarios that need to preserve messages past retention should plug into an `App\Support\LegalHoldRegistry::heldThreadIds()` set — the retention command excludes those thread IDs from the batch DELETE. (Registry implementation deferred to first real legal-hold need; the command structure leaves the seam.)

## Rate limiting + content moderation

| Layer | Limit | Source |
|---|---|---|
| HTTP throttle | 20/min/user (`throttle:messages` middleware) | `RateLimiter::for('messages')` in `AppServiceProvider`, configurable via `INBOX_RATE_LIMIT_PER_MINUTE` |
| Body length | 4000 chars | `StoreMessage*Request` validation, `config('inbox.body_max_length')` |
| Spam guard | URL repetition >5 with ≤2 unique URLs / non-printable fraction >50% / operator-curated `config('inbox.content.spam_tokens')` | `App\Support\MessageContentPolicy::isSpam`, called from both Form Requests via `withValidator->after` |

429 responses emit `inbox_rate_limit_hits_count` gauge. Spam rejections emit `inbox_spam_rejected_count` gauge.

## Offline-write queue (Phase 62 integration)

Inbox compose is the 6th named queue under the Phase 62 multi-route background-sync layer:

| Queue | Routes |
|---|---|
| `pm-offline-messages` | POST `/message-threads`, POST `/message-threads/{id}/messages`, POST `/tenant/inbox`, POST `/tenant/inbox/{id}/messages` |

`resources/js/composables/useBackgroundSync.ts` exposes `routeFamily: 'messages'` for the compose Vue pages to opt in. The conflict path (offline replay hits a locked thread) reuses the Phase 62 `RowVersion` trait pattern + the `version` column on `message_threads` so a stale replay surfaces 409 instead of overwriting.

## Operator procedures

### Spam-token list update

1. Add the new token to the `config('inbox.content.spam_tokens')` array in `config/inbox.php`.
2. Redeploy — no config-cache rebuild needed (Laravel config-cache rebuilds on deploy per `scripts/deploy.sh`).
3. Verify a sample spam-containing payload is rejected via `tests/Feature/Inbox/Phase63ModTest.php::test_spam_body_rejected_at_form_request_level` rerun with the new token.

### Manual thread lock (out-of-band)

If a thread needs to be locked outside the landlord UI (e.g. abuse report from external channel):

```sql
UPDATE message_threads SET status = 'locked', updated_at = NOW() WHERE id = ?;
INSERT INTO messages (thread_id, sender_id, body, message_type, created_at, updated_at)
VALUES (?, NULL, 'Thread locked by platform administrator', 'system', NOW(), NOW());
```

The Auditable trait emits an audit_logs row automatically. `MessagePolicy::create` then blocks new posts.

### Retention drift audit

```bash
php artisan messages:enforce-retention --dry-run
```

Reports the count that *would* be soft-deleted, without writing. Use during the first 30 days after onboarding a new compliance regime to verify the retention window aligns with operator expectations.

## Observability (Phase 67 INBOX-OBSERVABILITY)

`inbox:depth-rollup` runs daily at 04:35 Africa/Nairobi (after the retention sweep, so counts reflect post-purge truth) and emits platform-wide, DB-derived snapshot gauges:

| Gauge | Meaning |
|---|---|
| `inbox_threads_total` | All non-deleted threads across every landlord |
| `inbox_threads_open` | Threads with `status = open` |
| `inbox_read_ratio` | Fraction of participant inboxes fully caught up (`last_read_at >= last_message_at`); 1.0 when there are no participants |
| `inbox_messages_24h` | Messages created in the last 24h |
| `inbox_attachment_scans_24h` | Attachment rows persisted in the last 24h (clean, plus fail-open `scan_status=error`; infected uploads are never persisted) |
| `inbox_attachment_infected_24h` | Infected uploads blocked in the last 24h (counted from `audit_logs.event_type = inbox.attachment.infected`) |

These complement the real-time Prometheus counters emitted at the point of action — `inbox_search_queries_count`, `inbox_attachment_scan_infected_count`, `inbox_attachment_scan_error_count`, `inbox_spam_rejected_count`, `inbox_rate_limit_hits_count` — which are graphed via `rate()` and cannot be reconstructed from a daily cron.

### Attachment malware detected

The `inbox_attachment_infected` alert (sev2, page) fires when `inbox_attachment_infected_24h > 0`. A tenant or landlord uploaded a file the scanner flagged as malware; the upload was rejected at the gate, so **nothing infected reached the tenant disk and no `documents` row was created** (the scan runs before the persistence transaction — see `MessageAttachmentService::scan`). On-call steps:

1. Pull the offending events:
   ```sql
   SELECT user_id, auditable_id AS sender_id, metadata, created_at
   FROM audit_logs
   WHERE event_type = 'inbox.attachment.infected'
     AND created_at >= NOW() - INTERVAL 24 HOUR
   ORDER BY created_at DESC;
   ```
   `metadata` carries `thread_id`, `file_name`, and the scanner `signature`.
2. Confirm the production scanner is the real one — `INBOX_SCAN_DRIVER=clamav` (not `null`). A `null` driver in production means uploads are NOT being scanned; treat that as the incident.
3. Identify the sender (`auditable_id`). A single hit is usually a compromised end-user device; a burst from one sender is a deliberate abuse signal — consider locking the thread (see *Manual thread lock*) and disabling the account.
4. No cleanup of stored files is required (the gate blocks before persistence), but verify `inbox_attachment_scans_24h` looks sane — a collapse to 0 alongside infections can indicate the scanner is erroring and `fail_closed` is rejecting everything (check `inbox_attachment_scan_error_count`).

### Scanner unavailable (fail-closed)

If `INBOX_SCAN_FAIL_CLOSED=true` (default) and clamd is down, every attachment upload is rejected with `inbox.scan.unavailable` and `inbox_attachment_scan_error_count` climbs. Restore clamd; uploads recover automatically. Set `INBOX_SCAN_FAIL_CLOSED=false` only as a deliberate, temporary trade-off (accept-with-`scan_status=error`) during a clamd outage where blocking uploads is worse than deferring the scan.

## Chat UI (Phase 71 INBOX-NATIVE-UX)

The two message Show pages (`Pages/MessageThreads/Show.vue` landlord, `Pages/Tenant/Inbox/Show.vue` tenant) are thin wrappers over three shared presentational components plus one composable. There is no duplicated chat markup.

- **`Components/Inbox/ChatThread.vue`** — the scroll region. Owns message grouping (same sender within 5 min), day separators, the id-anchored unread divider (captured once at load so it never shifts as messages stream), the animated typing bubble (fed by the Phase-67 presence whisper, `motion-safe` only), scroll management (auto-stick to newest while at the bottom; a floating `chat-jump-latest` pill with an unread-below count when scrolled up), and a single `AttachmentLightbox`.
- **`Components/Inbox/MessageBubble.vue`** — one bubble: ownership alignment, grouped avatar/name, the quote block (`bubble-quote`), per-bubble delivery state (clock = sending, retry = failed, ✓/✓✓ = sent/seen), reaction pills + the emoji picker, and attachment rendering (image thumbnail → lightbox / file chip → download / neutral placeholder when `scan_status !== clean`).
- **`Components/Inbox/ChatComposer.vue`** — sticky auto-grow composer: Enter sends / Shift+Enter newline (IME-composition aware), attach tray, char counter, the dismissible reply preview, and the locked state.
- **`composables/useThreadStream.ts`** — the live state machine. Seeds from the Inertia prop, merges later reloads by id (and syncs reactions for known ids — the only post-create mutable field), `ingest()`s `.message.posted`, and owns the optimistic send lifecycle + the reaction toggle/remote-apply. **The Echo channel is owned by the page** (one `subscribePrivate`/`unsubscribe` per `inbox.thread.{id}`), which subscribes `.message.read`, `.message.posted`, and `.message.reacted` and feeds the composable. Pages post with `preserveState: true` so the streamed list survives the reload.

### Reply / reaction model + isolation

- **Reply** — `messages.reply_to_id` self-FK (`nullOnDelete`). `StoreMessageRequest` validates the quoted id `exists` AND is in the *same thread* (non-deleted), so a reply can never quote another thread's or tenant's message. `Message::toReplyPreview()` produces the compact `{id, sender_name, body(120)}` used identically by the show payload (`AttachesReplyPreviews` trait) and the `MessagePosted` broadcast. A reply whose original is later soft-deleted degrades to un-quoted (preview `null`).
- **Reaction** — `message_reactions` (unique `message+user+emoji`). `MessageReactionController@toggle` gates on `MessageThreadPolicy::view` (participant pivot) AND `message.thread_id === thread.id` (404), with the emoji constrained to `config('inbox.reactions')`. Toggle is race-safe via `createOrFirst`. `MessageReacted` broadcasts the authoritative post-toggle count `->toOthers()`. Dedicated `throttle:reactions` (120/min, `INBOX_REACTIONS_RATE_LIMIT_PER_MINUTE`) so rapid taps don't exhaust the 20/min compose budget.
- **Attachments** — served by `MessageAttachmentController@show`, authorised by **thread participation** (NOT `DocumentPolicy`, which denies tenants Message-attached documents). It re-checks the document belongs to the named message-in-thread, requires `scan_status === clean` and an existing file, then 302-redirects to a 5-minute signed URL (Phase-59 resolver). Routes: `message-threads.attachments.show` / `tenant.inbox.attachments.show`. Image `<img>` tags carry `referrerpolicy="no-referrer"`.

### Known deferrals

- A live-appended (broadcast/optimistic) message carries no `documents`, so an attachment shows only after the next reload. Text streams immediately.
- `VirtualMessageList` (Phase-64) is not wired into `ChatThread` — virtualising the grouped/day-separated list is a separate perf concern.
- Offline (Phase-62) sends leave the optimistic bubble `sending` until reconnect + reload.

## Cross-references

- [[project_propmanager_phase63_plan]] — cycle planning + commit ledger
- [[project_propmanager_phase71_plan]] — Phase 71 native chat UI cycle
- `docs/runbooks/offline.md` — Phase 62 offline-write queue contract
- `docs/runbooks/alert-thresholds.md` — `inbox_unread_fallback_count` + `inbox_rate_limit_hits_count` operator response
- `docs/runbooks/tenant-portal.md` — Phase 28 tenant ability matrix
- `docs/runbooks/frontend-authz-and-ux.md` — Phase 20 ability conventions
