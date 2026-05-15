# API Versioning + Deprecation Contract

PropManager's HTTP API (`/api/*`) is a published platform — third-party
integrators (QuickBooks Sync apps, landlord-built dashboards, ERP
adapters) build against it and ship to production. **The contract
between PropManager and its API consumers is documented here.**

Shipped by the Phase-25 [API-SURFACE] audit cycle (2026-05-15). The
machine-readable OpenAPI 3.1 spec lives at
`/api/v1/openapi.json`; the interactive docs UI is at `/docs`
(authenticated landlords + super-admins).

## Versioning policy

### Major versions are URL-prefixed

```
/api/v1/landlord/invoices   # major version 1
/api/v2/landlord/invoices   # major version 2
```

A **major version** is a backwards-incompatible change: a removed
endpoint, a removed response field, a narrowed type, a renamed
parameter, a changed status-code semantic. Major versions get a new
URL prefix.

A **minor version** is a backwards-compatible change: a new endpoint, a
new optional parameter, a new response field, a widened type, a new
event type. Minor versions ship on the same URL prefix — no consumer
action required.

### Minor + patch versions are NOT URL-versioned

PropManager does **not** emit a `Sec-WebSocket-Protocol`-style version
header for minor/patch. The OpenAPI spec at `/api/v1/openapi.json`
carries an `info.version` field (semver: `1.7.3`) that consumers can
read if they want explicit version visibility — but every request to
`/api/v1/*` is on the current minor.

### Why not header-based versioning?

URL-versioning is the industry default (Stripe, Twilio, GitHub) because
it survives load balancers + caches + curl + browser bookmarks. Header
versioning fails the "open this URL in a browser" test and confuses
operators who don't know which `Accept` header the docs assumed.

---

## Deprecation contract

When a route or response field is going to be removed:

1. **Sunset header** points to the removal date (RFC 8594 IMF-fixdate
   format, e.g. `Wed, 11 Nov 2026 23:59:59 GMT`).
2. **Deprecation header** is `true` OR a date string (the day the
   deprecation flag was added).
3. The **API-deprecations changelog** (`docs/api/deprecations.md`)
   gains an entry with: deprecated_at, sunset_at, route(s), upgrade
   path, replacement endpoint.
4. **Minimum 6 months** between the `Deprecation` flag and the route's
   removal. Operators MUST NOT shortcut this — a consumer that built
   their integration 18 months ago needs runway to switch.
5. **Consumer notification**: a system job reads
   `personal_access_tokens` that have hit the deprecated route in the
   last 30 days and queues an email to the token owner. The deprecation
   reason + upgrade path + sunset date go in the body.
6. **Parallel-version support**: `v1` continues to respond 200 (with
   the deprecation headers) right up to the sunset date. After the
   sunset date, the route returns **410 Gone** with a problem+json body
   pointing at the replacement.

### What gets headers

Every response from a deprecated route — 200, 4xx, 5xx — MUST carry
both headers. The `ApiVersionHeaders` middleware reads the route's
`deprecated_at` action attribute (e.g.
`Route::get('/old', ...)->middleware('deprecated:2026-12-31')`) and
emits them automatically. Consumers SHOULD log a warning when they see
`Deprecation: true` on a response.

### What 410 Gone looks like

After the sunset date the route returns:

```json
{
    "type": "https://propmanager.test/errors/api-route-gone",
    "title": "API route removed",
    "status": 410,
    "detail": "The /api/v1/landlord/reports/arrears endpoint was sunset on 2026-11-11 in favour of /api/v2/landlord/reports/arrears. See https://propmanager.test/docs/api/deprecations.md.",
    "replacement": "/api/v2/landlord/reports/arrears",
    "deprecated_at": "2026-05-15",
    "sunset_at": "2026-11-11"
}
```

---

## Examples

### v1 → v2: report shape change (a hypothetical real one)

Suppose the v1 `/api/v1/landlord/reports/arrears` response is flat:

```json
[{ "tenant_id": 1, "amount": 5000, "due_date": "2026-04-01" }]
```

v2 wants a paginated, totals-bearing shape:

```json
{
    "data": [{ "tenant_id": 1, "amount": "5000.00", "due_date": "2026-04-01" }],
    "meta": { "total": 17, "total_amount": "47350.00", "page": 1, "per_page": 20 },
    "links": { "first": "...", "last": "...", "next": "...", "prev": null }
}
```

This is a **major version**. Operator path:

1. Add `/api/v2/landlord/reports/arrears` returning the new shape.
   `/api/v1/*` keeps returning the flat array.
2. In the same PR, add `->middleware('deprecated:2026-11-11')` to the
   v1 route. The `ApiVersionHeaders` middleware now emits
   `Deprecation: true` + `Sunset: Wed, 11 Nov 2026 23:59:59 GMT` on
   every v1 response.
3. Add an entry to `docs/api/deprecations.md` with upgrade-path link.
4. Queue the consumer-notification email job; verify it lists the
   correct token owners.
5. After `sunset_at`, the v1 route's middleware returns 410 with the
   problem+json body (no more 200s — the upgrade window is over).
6. After ~30 days of 410s, the route can be deleted from `routes/api.php`.

### Field-level deprecation (minor)

Suppose `/api/v1/landlord/invoices/{id}` returns
`{ "old_total": "5000", "new_total": "5000.00" }` and you want to
remove `old_total`:

1. Add `Deprecation` + `Sunset` headers via route middleware (same as
   above). Don't change the response — the field still flows.
2. Add an entry to `docs/api/deprecations.md` describing the field
   removal + sunset date.
3. Consumer-notification email goes out.
4. At `sunset_at`, the field is removed from the JsonResource. The
   route now returns the slimmer shape.

---

## CI gates

Phase-25 watchdogs (`tests/Feature/Api/Phase25*Test.php`) guard the
spine:

| Test | What it guards |
|------|----------------|
| `Phase25DocTest` | OpenAPI spec served at `/api/v1/openapi.json`, valid 3.1.x, every documented endpoint present; `/docs` UI gated to landlord+ |
| `Phase25VersionTest` | `ApiVersionHeaders` middleware emits Sunset + Deprecation on routes marked `deprecated:*` |
| `Phase25AuthTest` | API-key UI (list / mint / revoke) honours role gates; last_used_ip tracking; SecurityLog entries on token lifecycle; 12-month default expiration |
| `Phase25RateLimitTest` | X-RateLimit-* headers emitted on every throttled response; 429 body is problem+json with retry_after_seconds |
| `Phase25WebhookTest` | Outbound webhook HMAC-SHA256 signing, retry-with-exponential-backoff, dead-letter after 5 attempts, event filter respected |
| `Phase25ErrorTest` | Every `/api/v1/*` error shape conforms to RFC 7807 problem+json |
| `Phase25CiTest` | Routes-vs-spec drift watchdog — every API route is in the spec, every spec endpoint resolves, every Api controller method returns a JsonResource (shrink-only allow-list for known-partial endpoints) |

---

## Internal operator FAQ

**Q: Can I add a v2 endpoint without bumping v1?**
Yes. Adding `/api/v2/foo` is fine; the relationship between v1 and v2 is
"different majors of the same API," not "v2 replaces v1." Existing v1
endpoints keep running until they get a `Sunset` header.

**Q: Do I need a deprecation header for an endpoint we never advertised?**
Yes, if any PAT has hit it. The `personal_access_tokens.last_used_at`
column is the source of truth for "has any integrator built against
this." If the answer is no (some internal admin endpoint), the
deprecation contract still applies but the consumer-notification email
job will find zero recipients — harmless.

**Q: Can I shortcut the 6-month window?**
Only for **security-driven removals** — a CVE, a credential-leak fix,
an authentication-bypass patch. In that case: ship the fix, document
the shortcut in `docs/api/deprecations.md` with the CVE link, and the
consumer-notification email goes out with an "immediate action
required" subject line. Anything else waits 6 months.

**Q: Where do I document a NEW endpoint?**
Nowhere — the OpenAPI spec is generated from the route + FormRequest +
JsonResource. Add the route, add a FormRequest with validation rules,
add a JsonResource for the response, and Scramble (dedoc/scramble)
picks it up. The CI artifact at every push includes the regenerated
spec; reviewers see the spec diff in the PR.

---

## Files of interest

- `routes/api.php` — the route surface
- `app/Http/Controllers/Api/*.php` — the controllers
- `app/Http/Resources/*.php` — the response shapes (JsonResource)
- `app/Http/Requests/Api/*.php` — the request shapes (FormRequest)
- `app/Http/Middleware/ApiVersionHeaders.php` — Sunset + Deprecation emission
- `app/Http/Middleware/ApiRateLimitHeaders.php` — X-RateLimit-* emission
- `config/scramble.php` — OpenAPI generator config (after Phase 25 DOC-1)
- `docs/api/deprecations.md` — the consumer-facing deprecation changelog
- `tests/Feature/Api/Phase25*Test.php` — the watchdog suite
