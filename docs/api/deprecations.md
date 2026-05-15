# API deprecations changelog

Consumer-facing list of every deprecated route + response field in the
PropManager API. Entries are added when a route gains the
`Deprecation` + `Sunset` headers; removed when the sunset date passes
and the route returns 410.

For the operator process behind these entries, see
[`docs/runbooks/api-deprecation.md`](../runbooks/api-deprecation.md).

For the machine-readable spec, see `/api/v1/openapi.json`.

## Active deprecations

_None._ Phase 25 ships the deprecation contract — no routes are
currently deprecated.

## Schema

Each entry below documents:

- `route` — the endpoint path + method
- `deprecated_at` — date the Deprecation header was added (YYYY-MM-DD)
- `sunset_at` — date the route stops responding 200 (YYYY-MM-DD)
- `replacement` — the endpoint path that replaces it (if any)
- `upgrade_doc` — link to migration guide
- `reason` — brief operator justification

### Format example

```markdown
### GET /api/v1/landlord/reports/arrears

- **deprecated_at**: 2026-05-15
- **sunset_at**: 2026-11-11
- **replacement**: `GET /api/v2/landlord/reports/arrears`
- **upgrade_doc**: [v2-arrears-migration.md](./migrations/v2-arrears-migration.md)
- **reason**: v2 adds pagination + totals + breaking shape change to support landlords with >1000 tenants. v1's flat array streams the full result set which OOMs the client for large portfolios.
```

## Sunset (removed) routes

_None._ When a route's sunset date passes and the operator deletes the
route from `routes/api.php`, its entry moves down here and stays for
historical reference. The route itself returns 410 Gone with a
problem+json body pointing at the replacement (see the operator runbook
for the exact response shape).
