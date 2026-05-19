# Frontend polish — conventions and recipes

Owner: Frontend / UX workstream.
Last touched: Phase 51 — VUE-TAIL-1.

This runbook captures the conventions Phase 51 established so future
contributors land Vue polish consistently. Most items here are very
small; collecting them in one place keeps drift low.

---

## 1. Polling contract

Standard polling pattern (used by Scheduled.vue preview pane, see
[[reports.md]] section 1):

| Concern         | Convention                                                       |
|-----------------|-------------------------------------------------------------------|
| Cadence         | 15 seconds (matches the Phase-50 REAL-TIME-PREVIEW finding)      |
| Visibility-aware| `document.visibilitychange` listener — pause on hidden tab        |
| Pause counter   | Increment a session-local `pollPauseCount` ref when pausing      |
| Retry           | 3 attempts with exponential backoff: 1s → 2s → 4s                |
| Permanent fail  | 4xx responses skip the retry loop (validation errors are sticky) |
| Cleanup         | `onUnmounted` clears the interval + removes the listener         |

A future polling surface should reuse this exact pattern. Drift here
re-introduces the bandwidth-waste bug Phase 51 closed.

## 2. Drill-down navigation (Builder.vue)

`drillContext` is an optional prop emitted by
`BuilderController::drill`. When present, Builder.vue:

1. Renders an indigo banner showing parent_name + drill_field + segment.
2. Pre-populates the form state from `drillContext.config`.
3. Pre-fills `rows.value` from `drillContext.rows`.
4. Highlights the drill_field column (indigo tint + chevron).

For the outer (non-drill) mode, a SavedReport row with `drill_field`
set gains a "Drill mode" button — clicking loads its config + runs
preview + makes rows clickable; clicking a row navigates to
`reports.builder.drill` with the row's drill-field cell value as
segment.

Adding a new drillable field:
1. Add the field to `ReportBuilderService::ALLOWED_FIELDS` with `'type' => 'string'` (only string fields can be a drill segment).
2. Existing Builder.vue logic auto-detects via `drillFieldColumnKey` computed.
3. No additional Vue change required.

## 3. Lease-counter component slots

Three components under `resources/js/Components/LeaseCounter/`:

| Component                    | Props                          | Slot location                          |
|------------------------------|--------------------------------|----------------------------------------|
| `CounterOfferStatusBadge`    | `status`, `label?`             | Anywhere a lease_renewals row renders  |
| `CounterOfferCountdown`      | `expiresAt`                    | Next to status badge on tenant/landlord lease pages |
| `CounterOfferHistory`        | `history[]`                    | Lease detail page "Negotiation history" section |

Status colors:
- `counter_proposed` → indigo (waiting)
- `accepted` → emerald (closed positive)
- `declined` → rose (closed negative)
- `expired` → gray (window lapsed)

The countdown auto-ticks every 60s and switches to amber when <72h
remain. It cleans up its setInterval on unmount.

## 4. Wizard styling convention (Phase-31 brand)

The branded wizard look is: `bg-gradient-to-br from-indigo-50 via-white
to-purple-50` outer + `bg-white rounded-2xl shadow-sm ring-1
ring-gray-100 p-8` inner card. For role-picker / payment-method-picker
card grids:

- Selected state: `bg-gradient-to-br from-indigo-100 via-white to-purple-100 ring-2 ring-indigo-500 text-indigo-900`
- Unselected:    `bg-white border border-gray-200 text-gray-600 hover:border-indigo-300`

Use inline SVG (not asset imports) for icon cards — keeps the bundle
small and avoids icon-library version drift. The three reference
patterns are the role picker (Register.vue) + payment-method picker
(TenantSteps step 3) + caretaker accept/decline radio (CaretakerSteps
step 2).

## 5. Plain-text email fallback recipe

Mailables that ship to users (not just devs) should declare BOTH a
markdown view AND a text view:

```php
return new Content(
    markdown: 'emails.foo.bar',
    text:     'emails.foo.bar-text',
    with: [/* ... */],
);
```

Why: text-only mail readers and accessibility tools render Laravel's
auto-converted markdown poorly. The explicit text view lets us control
logical reading order + link context.

Naming: `{slug}-text.blade.php` co-located in the same directory as
the markdown view.

## 6. Adding a new payment-method icon

The card-grid picker in `Pages/Onboarding/TenantSteps.vue` step 3
loops over an array of `{value, label}` tuples and renders an inline
`<svg>` per type. To add a new method:

1. Add `{value: 'paypal', label: 'PayPal'}` to the v-for array.
2. Add a `<svg v-else-if="option.value === 'paypal'">` branch.
3. Update the matching service-layer validator (`TenantOnboardingService::processPaymentMethod`).

The icon should be a 24x24 viewBox with `h-6 w-6 text-indigo-500` class.

## 7. Visibility-aware metric

The `vue_preview_poll_pause_count` counter increments client-side every
time the polling pauses for visibility. It's currently visibility-only
(no telemetry wiring). When ops wants to know whether the optimisation
is paying off, the gauge will need a small POST endpoint that accepts
the session-local count on page unload. Deferred until the frontend
telemetry pipeline exists.

---

## 8. Phase-64 VUE-TAIL-2 patterns

The following mount + polish patterns shipped in Phase 64 [VUE-TAIL-2] (sequel to Phase 51 VUE-TAIL-1).

### 8.1 InboxBell mount recipe
`Components/InboxBell.vue` mirrors `NotificationBell.vue` shape — a single icon button reading `$page.props.auth.inbox_unread_total` (shared by Phase 63 `HandleInertiaRequests`). Mount in `AuthenticatedLayout.vue` right of NotificationBell. Click navigates to `/tenant/inbox` for tenants, `/message-threads` for landlord/caretaker.

### 8.2 ConflictDialog global mount via writeConflictBus
Mount once in `AuthenticatedLayout.vue` as a state-controlled component. `resources/js/lib/writeConflictBus.ts` is the event-emitter; `onMounted` registers a handler that flips dialog open state on every `emit`. `app.js` forwards SW `WRITE_CONFLICT_409` messages into the bus. `sw.ts` `registerOfflinePost`'s `onSync` uses a manual `shift+fetch` loop that branches on `response.status === 409` to detect conflict + emit the message.

### 8.3 PendingSyncBadge per-page wiring
`<PendingSyncBadge route-family="invoices" :resource-id="invoice.id" />` in the page header alongside the resource title. The badge reads `queuedOps.hasPendingFor(family, id)` and renders an amber chip when a queued POST targets that resource — closes the Phase 62 "looks normal but didn't reach the server" trust gap.

### 8.4 AttachmentPreviewList + drag-drop
`Components/Inbox/AttachmentPreviewList.vue` renders preview chips: image MIMEs get `URL.createObjectURL` thumbnails (revoked on unmount + per-row remove); non-image MIMEs render `DocumentIcon` + filename + size. Pair with `composables/useFileDropZone` (native HTML5 dragover/drop, no external library) for drag-and-drop upload.

### 8.5 Virtualization for long lists
`Components/Inbox/VirtualMessageList.vue` — no-op below 100 messages, 60-message window + 20-message buffer above with IntersectionObserver-driven older-window load. Reuse for any Vue page rendering hundreds of repeated items on low-end Kenyan mobile.

### 8.6 PWA telemetry sendBeacon transport
`resources/js/lib/pwaTelemetry.ts` accumulates counters via `increment(metric, value, labels)` + flushes via `navigator.sendBeacon` on `visibilitychange` + `beforeunload`. Endpoint: POST `/api/v1/telemetry/pwa` (auth:sanctum + throttle:telemetry 60/min, allow-list-gated metrics, Prometheus label-name regex on label keys). `app.js` calls `registerPwaTelemetry()` once at boot.

---

See [[reports.md]] for the reports-surface companion runbook,
[[onboarding.md]] for the wizard service-layer architecture, and
[[inbox.md]] for the Phase 63 [COMMUNICATION-INBOX] operator runbook.
