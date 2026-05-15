# PWA operator runbook

Phase-26 [MOBILE + PWA] shipped PropManager as an installable
Progressive Web App. This runbook covers the day-to-day operator
concerns: how the service worker versions itself, how to force a
stuck client, how to debug cache state, and the per-route-family
caching contract.

For the consumer-facing changes (install prompt, offline tolerance),
see the in-app help center.

## Architecture in one paragraph

`vite-plugin-pwa` (configured in `vite.config.js`) runs in
`injectManifest` strategy: it compiles
`resources/js/sw-merged.ts` into `public/build/sw.js` at build time
and injects the precache manifest (every Vite-built asset hash) into
`self.__WB_MANIFEST`. The Laravel route `GET /sw.js`
(`routes/web.php`) streams that file with
`Service-Worker-Allowed: /` so the SW gets root scope despite living
under `/build/`. Registration happens in `resources/js/app.js` at
`window.load`.

## Versioning + cache invalidation

Workbox keys every cache by the precache-manifest revision (the build
hash). When a deploy rotates asset hashes:

1. `precacheAndRoute(self.__WB_MANIFEST)` sees new entries.
2. `cleanupOutdatedCaches()` deletes the previous cache after the new
   one is fully populated.
3. `registerType: 'autoUpdate'` makes the SW take control on the next
   navigation (no `Skip waiting` prompt UI shipped — we accept the
   one-page-lag because rolling deploys usually have a few minutes
   of overlap anyway).

**Manual SW reset** (for a stuck client):

```js
// In DevTools console on the affected page
navigator.serviceWorker.getRegistration().then(r => r?.unregister());
caches.keys().then(keys => keys.forEach(k => caches.delete(k)));
location.reload(true);
```

## Runtime caching contract

Each route family has a Workbox strategy. The contract:

| Route family               | Strategy             | Cache name        | Max age   | Why |
|----------------------------|----------------------|-------------------|-----------|-----|
| `GET /build/*`             | CacheFirst           | `pm-build-assets` | 1 year    | Hash in filename = cache key; deploys rotate the hash so a stale asset can never serve fresh content. |
| `https://fonts.bunny.net/*`| CacheFirst           | `pm-fonts`        | 30 days   | Fonts rotate slowly; re-downloading on every session burns Kenyan mobile data. |
| `request.destination === 'image'` | StaleWhileRevalidate | `pm-images` | 7 days | Tenant photos + logos: stale-but-shown is better than blocking. |
| `GET /api/v1/*` (excl. auth/webhooks) | StaleWhileRevalidate | `pm-api-reads` | 5 min | Read endpoints are safe to cache briefly; mutations bypass (only GET matches). |
| Navigation (HTML)          | NetworkFirst (4s)    | `pm-navigation`   | 1 day     | Fall back to `/offline` if the network or origin server can't respond in 4s. |

**Adding a new route family**: pick a strategy from the Workbox
strategies set (`CacheFirst`, `NetworkFirst`, `StaleWhileRevalidate`,
`NetworkOnly`, `CacheOnly`). The rule of thumb:

- **CacheFirst** — content never changes for a given URL (hashed
  assets, fonts with versioned URLs).
- **NetworkFirst** — content updates often but offline tolerance
  matters (HTML navigation, real-time data).
- **StaleWhileRevalidate** — content updates occasionally and a
  brief staleness is acceptable (lists, dashboards).
- **NetworkOnly** — content is sensitive or mutation-intent
  (auth flows, payments, webhooks). The plugin defaults to this for
  any unmatched request.
- **CacheOnly** — content is precached and never refetched from
  network (rare; only the install-time bundle).

Then add the rule in `resources/js/sw-merged.ts` and document it in
the table above.

## Offline navigation fallback

`NavigationRoute` with a 4-second timeout: if the origin doesn't
respond in 4s, the SW serves `/offline` (precached as part of the
shell). The denylist (`/api/`, `/docs/`, `/admin/`, `/livewire/`,
`/webhooks/`, `/sanctum/`) keeps these paths from being hijacked
into the SPA shell — they have their own failure semantics
(RFC 7807 problem+json from Phase-25 ERROR-1 for API; Sanctum 401s
trigger the existing redirect logic).

## Push notifications

`sw-merged.ts` folds in the pre-Phase-26 push handlers verbatim. The
backend infra (`push_subscriptions` table, `PushNotificationService`,
`NotificationsController` push endpoints, `usePushNotifications`
composable) is unchanged. The handlers:

- `push` — render a notification from the push payload
- `notificationclick` — navigate the user to the deep-link URL
- `notificationclose` — best-effort dismissed-event telemetry
- `pushsubscriptionchange` — re-register the subscription when the
  browser rotates the endpoint
- `message` with `{ type: 'SKIP_WAITING' }` — force activate (used
  by the auto-update flow on slow rollouts)
- `message` with `{ type: 'SET_VAPID_KEY' }` — receive the
  application-server key from the host page

## CI gates

- **Lighthouse PWA score ≥ 90** — see Phase 1d. Runs against
  `/dashboard` with an authenticated cookie via `@lhci/cli` in the
  Playwright sibling job.
- **Service-worker integration spec** — Playwright forces an offline
  navigation and asserts the offline page renders (not the browser
  default offline screen).
- **Install-prompt regression spec** — asserts `manifest.json` is
  reachable, has 192×192 + 512×512 icons, and the blade has the
  `<link rel="manifest">` declaration.

If any gate fails, fix the root cause — never just disable the gate.
PWA quality erodes silently otherwise.

## Common debugging recipes

**The new SW won't activate** — DevTools → Application → Service
Workers → ✅ "Update on reload". Reloading once forces SkipWaiting.

**A cached response is stale** — DevTools → Application → Cache
Storage → expand the cache → right-click the entry → Delete. The
next request repopulates.

**The offline page is served on a real navigation** — likely the
origin is responding too slowly. Bump `networkTimeoutSeconds` in
`sw-merged.ts` (currently 4) or audit the slow endpoint via Phase-22
SLO dashboard.

**The SW never registers** — check the console: a hard refresh
(Ctrl-Shift-R / Cmd-Shift-R) bypasses the SW and shows the
registration error. Common causes: `/sw.js` route 404s because
`npm run build` hasn't run; the SW source has a TypeScript error.

## Related runbooks

- `docs/runbooks/api-deprecation.md` — Phase-25 API deprecation contract
- `docs/runbooks/accessibility.md` — Phase-23 a11y baseline
- `docs/runbooks/i18n.md` — Phase-24 i18n two-engine model
