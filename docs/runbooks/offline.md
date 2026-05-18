# Offline PWA runbook

PropManager runs as a Progressive Web App. This runbook is the umbrella
map of every offline-related surface, written for operators triaging
"my submit appears to hang" / "queued op never sent" / "shell shows
stale data" tickets, and for new devs landing in the offline code path.

The PWA surface stretches across **three audit cycles**:

- **Phase 26 [MOBILE + PWA]** (2026-05-15) — shell + offline page + push
  notifications + idb-keyval per-user store + invoice background-sync
  + useConnection + QueuedOpsTray.
- **Phase 37 [PWA-DEPTH]** (2026-05-16) — useWebPush composable +
  WeeklyInsightDigest + Settings/Notifications.
- **Phase 62 [PWA-OFFLINE-DEPTH]** (2026-05-18, this cycle) — extends
  background-sync to tickets/comments/readings/payments + persistent
  IDB write queue + dead-letter + ticket photo blob queue + per-route
  cache strategies + version-based conflict detection + slow-network
  banner + per-resource pending-sync badge + manual Sync Now trigger.

If you're new, read top to bottom. If you're triaging, jump to the
section matching the surface that broke.

---

## Layer 1: the service worker

Source: `resources/js/sw.ts` (Workbox-managed, compiled by
vite-plugin-pwa).

The SW does five jobs:

1. **Shell cache** — `pm-shell-v1` NetworkFirst 7d. Navigation requests
   for non-API paths hit network first with a 4s timeout; cached
   responses serve when offline. The `/dashboard` route is precached
   at SW install so the very first offline navigation has a shell.
2. **Build assets** — `pm-build-assets` CacheFirst 365d. Vite's
   content-hash naming makes this safe forever.
3. **Per-route-family API read caches** (Phase 62 CACHE-STRATEGY-1):
   | Route pattern | Strategy | TTL | Cache |
   |---|---|---|---|
   | `/dashboard` | NetworkFirst | 30s | `pm-api-dashboard` |
   | `/api/v1/{currencies,plans,countries}` | CacheFirst | 7d | `pm-api-static-lookups` |
   | `/api/v1/{resource}/{id}` | StaleWhileRevalidate | 2min | `pm-api-detail` |
   | `/api/v1/{resource}` (list pages) | StaleWhileRevalidate | 5min | `pm-api-list` |
   | `request.destination === 'image'` | StaleWhileRevalidate | 7d | `pm-images` |
   | `fonts.bunny.net` | CacheFirst | 30d | `pm-fonts` |
4. **Background-sync POST queues** (Phase 62 OFFLINE-WRITES-1/2):
   | Queue name | Matches |
   |---|---|
   | `pm-invoice-queue` | `url.pathname.startsWith('/invoices')` |
   | `pm-offline-tickets` | `url.pathname === '/tickets'` |
   | `pm-offline-comments` | `/^\/tickets\/\d+\/comment$/` |
   | `pm-offline-readings` | `url.pathname === '/readings'` |
   | `pm-offline-payments` | `url.pathname === '/payments/record'` |
5. **Push notification handlers** (Phase 37) — unchanged.

The SW also handles **four message types** posted from the host page:

| Type | Payload | Effect |
|---|---|---|
| `SKIP_WAITING` | — | Calls `self.skipWaiting()` so a stuck client picks up a new SW build immediately. |
| `SET_VAPID_KEY` | `{ key: string }` | Stores the VAPID public key so push subscriptions can be rebuilt. |
| `CACHE_BUST` | `{ routeFamily: string }` | Invalidates the matching SWR cache after a successful POST replay — see CACHE-STRATEGY-3. |
| `SYNC_NOW` | — | Broadcasts BG_SYNC_DRAINED for every known queue. The user's manual "Sync now" trigger in QueuedOpsTray. |

---

## Layer 2: client-side composables and stores

| Module | Phase | Responsibility |
|---|---|---|
| `composables/useBackgroundSync.ts` | 26 + 62 | Wraps `axios.post` with `X-Idempotency-Key` + records to queuedOps + persists to offlineWriteQueue + throws QueuedOfflineError on network failure. Takes `options.routeFamily` to route into the right queue. |
| `composables/useConnection.ts` | 26 | Wraps `navigator.connection` with vendor-prefixed fallbacks. Exposes `effectiveType`, `saveData`, `downlink`, `rtt`, `isSlow`. Default `'4g'` in unsupported browsers (Firefox / Safari). |
| `composables/useWebPush.ts` | 37 | VAPID key fetch + auto-resubscribe on key rotation. |
| `composables/useOfflineData.ts` | 26 | Per-user TTL cache reader that pairs with `OnlineIndicator` for UX. |
| `lib/offlineStore.ts` | 26 | idb-keyval wrapper, per-user keyspace (`pm:${userId}:${landlordId}:${key}`). |
| `lib/offlineWriteQueue.ts` | 62 | Persistent write queue with three IDB stores (queue / dead-letter / replay-log). `MAX_ATTEMPTS=5` before dead-letter eviction. |
| `lib/offlinePhotoStore.ts` | 62 | Ticket annotation blob queue. `PHOTO_BUDGET_BYTES=50MB` enforced via oldest-first eviction. Throws `PhotoQuotaExceededError` when even after eviction the incoming blob would not fit. |
| `stores/queuedOps.ts` | 26 + 62 | Pinia store: `add` + `cancel` + `drain` + `markDeadLetter`. Selectors: `count` + `deadLetterCount` + `hasPendingFor(routeFamily, resourceId)`. |

---

## Queue lifecycle (Phase 62 OFFLINE-WRITES-3)

```
enqueue → attempt 1 → ... → attempt 5 → dead-letter
                       ↓
                    success → replay-log
```

- **enqueue**: useBackgroundSync catches a network error, adds to
  Pinia queuedOps + writes IDB entry with `attempts: 0`.
- **attempt N**: Workbox's BackgroundSyncPlugin replays the request
  on reconnect. On 200, `recordReplaySuccess` deletes the queue entry
  and writes a ReplayLogEntry. On 4xx/5xx, `recordReplayAttempt`
  bumps `attempts`.
- **dead-letter**: when `attempts >= MAX_ATTEMPTS` (5), the entry is
  moved from `queue` store to `dead-letter` store. The Pinia store's
  `markDeadLetter` surfaces it in QueuedOpsTray's "Permanently failed"
  section. The user can manually discard via the tray's cancel button.
- **replay-log**: last 50 successful replays, pruned oldest-first.
  Useful for audit / debugging "did my write actually go through".

The `recordReplaySuccess` hook in `app.js` runs on every
`BG_SYNC_DRAINED` message from the SW.

---

## Conflict resolution (Phase 62 CONFLICT-RESOLUTION-1/2/3)

When a queued POST replays after a long offline window, the resource
may have been edited by another device. PropManager handles this
with **optimistic concurrency**:

- `tickets`, `ticket_comments`, `water_readings` all carry a
  `version` column (default 1, incremented on every save by the
  `RowVersion` trait).
- Controllers handling the write path call
  `$model->assertIfMatch($request->header('If-Match'), $payload)`.
- If versions diverge, `WriteConflictException` fires; the
  `bootstrap/app.php` render hook turns it into a 409 JSON response
  with `{ error: 'write_conflict', current_version, current, incoming, diff }`.

Client side: `Components/Offline/ConflictDialog.vue` surfaces three
paths to the user:

| Action | Effect |
|---|---|
| Discard my change | Drop the queued op, keep the server's version. |
| Merge selected fields | Per-field radio buttons pick current vs incoming. |
| Overwrite server version | Re-POST with the now-current version, bumping over the server. |

---

## Offline photos (Phase 62 OFFLINE-PHOTOS-1/2/3)

`TicketPhotoAnnotator.vue` (Phase 45) now persists each canvas
snapshot to `pm-offline-photos` IDB **before** uploading. This means
a network blip doesn't throw away the annotation — the blob stays in
IDB as a retry handle. Lifecycle:

1. `canvas.toBlob` → `enqueuePhoto({ticketId, documentId, blob, annotationData})`
   returns a key.
2. `markUploading(key)` → router.post fires.
3. On success: `discardPhoto(key)` deletes the IDB entry.
4. On Inertia error: `markFailed(key, JSON.stringify(errors))`.

`Components/Offline/PhotoUploadStatusList.vue` renders the list
filtered to the current ticket, with status pills (pending / uploading
/ failed) + per-row cancel button.

**Budget**: `PHOTO_BUDGET_BYTES = 50MB`. Enforced via
`enforceBudget(maxBytes, incomingBytes)` which evicts oldest-first
sorted by `createdAt`. Throws `PhotoQuotaExceededError` if even after
evicting every entry the incoming blob would still exceed the cap;
the annotator catches this and falls through to upload-only path so
the user's save attempt still proceeds.

---

## Connectivity UX (Phase 62 CONNECTIVITY-UX-1/2/3)

- **SlowNetworkBanner** (`Components/Layout/SlowNetworkBanner.vue`):
  amber bar above `<main>` in `AuthenticatedLayout`. Renders when
  `useConnection.isSlow === true`. Dismissable per-session via
  `localStorage` key `pm.slow_banner.dismissed_until` (5-min window).
- **PendingSyncBadge** (`Components/Offline/PendingSyncBadge.vue`):
  amber chip with pulsed dot. Takes `{ routeFamily, resourceId }`
  props and uses `queuedOps.hasPendingFor()` to filter. Drop into
  any resource detail page header.
- **QueuedOpsTray "Sync now"** button posts `SYNC_NOW` to the active
  SW; rate-limited 1.5s to prevent spam; disabled while offline.

---

## Operator runbook: inspecting the queues

The dead-letter and pending queues are IDB databases — inspect them
via browser DevTools:

1. Open DevTools → Application → IndexedDB.
2. Look for these databases:
   - `pm-offline-writes` (queue + dead-letter + replay-log stores)
   - `pm-offline-photos` (photos store)
   - `keyval-store` (Phase-26 per-user offlineStore)
3. To clear a stuck dead-letter entry: right-click the entry → Delete.
4. To force a SW update on a stuck client: `await navigator.serviceWorker.getRegistration().then(r => r.update())`
   in the DevTools console.

If a user reports "my writes vanished":

1. Check Application → IndexedDB → `pm-offline-writes` → `queue` for
   entries with high `attempts` count. They're probably retrying
   against a 4xx your controller is returning.
2. Check `pm-offline-writes` → `dead-letter` for entries that already
   exceeded MAX_ATTEMPTS. The `lastError` field tells you why.
3. Replay-log is the audit trail of successful replays.

---

## Alert gauges (visibility-only)

| Gauge | Source | Signal |
|---|---|---|
| `offline_writes_dead_letter_count` | offlineWriteQueue | Sustained > 0 means clients are failing replay repeatedly. Inspect the replay path of the failing routeFamily. |
| `offline_photo_quota_evictions_count` | offlinePhotoStore | Users hitting the 50MB photo budget — consider raising or surfacing storage usage in UI. |
| `offline_shell_boot_count` | SW navigation handler | How often the offline shell satisfies a navigation. A high count means a meaningful fraction of users boot offline. |

Wiring of these emit points lives in the host-page bridge (app.js)
and a Phase-63+ telemetry follow-up.

---

## Cross-links

- [pwa.md](pwa.md) — Phase 26 PWA shell + push contract.
- [cache.md](cache.md) — Phase 57 L7-CACHE + Phase 62 per-family
  cache-key fragmentation contract.
- [Phase 26 plan](../../phase-26-audit-prd.json), [Phase 37 plan](../../phase-37-audit-prd.json),
  [Phase 62 plan](../../phase-62-audit-prd.json).
