# Vendor portal — operator runbook

Phase-70 VENDOR-PORTAL gives external contractors (the standalone `Vendor`
model — no User row) a self-service portal layered on the Phase 49/54
assignment surface.

## Authentication model

- A Vendor has **no User row**. The portal identity lives in the **session**,
  seeded by a **signed magic-link**: `GET /v/portal/enter/{vendor}`
  (`vendor.portal.enter`, `signed` + `throttle:invitation`, 7-day TTL minted
  by `VendorPortalLinkService::issue`). `enter` verifies the signature,
  `session()->regenerate()` (anti-fixation), stores `vendor_portal_id`, redirects.
- `EnsureVendorPortal` (`vendor.portal` alias) guards `/v/portal/*`: it
  re-resolves the vendor from the session **and re-checks `is_active` every
  request**, so deactivating a vendor revokes access immediately. The vendor
  is stashed on `request->attributes('portal_vendor')` — controllers read it
  from there and **never** from a client/route id (the isolation boundary).
- `POST /v/portal/logout` clears the session.

### Re-issuing a link (links expire after 7 days)

- Landlord UI: `POST /finances/vendors/{vendor}/portal-link`
  (`finances.vendors.portal-link`, VendorPolicy::update — owning landlord only;
  cross-tenant → 403). Emails a fresh `VendorPortalLinkMailable`.
- Operator CLI: `php artisan vendor:portal-link {id} --send`.

## Isolation contract (the security spine)

Every portal query scopes to the session vendor:
- Inbox lists `Ticket::where('vendor_id', portal_vendor->id)`.
- Accept/decline/log-time/resolve re-verify `ticket.vendor_id === portal_vendor->id`
  (403) before acting; accept/decline also re-assert `pending` under
  `lockForUpdate` (race guard).
- The statement + SLA aggregate only the session vendor's rows; the job page
  shows only **this** vendor's time logs (a reassigned ticket never leaks a
  prior vendor's entries).

## Job lifecycle

`tickets.vendor_status` (pending → accepted | declined) + `vendor_responded_at`.
Assignment sets `pending`. **Accept** acknowledges the ticket. **Decline**
clears `vendor_id` (back to the landlord pool) + fires
`VendorDeclinedAssignment` → `NotifyLandlordOnVendorDecline` emails the
landlord. On an **accepted** open job the vendor logs time (`ticket_time_logs`,
≤1440 min/entry, `throttle:10,1`) and marks it **resolved**
(status=Resolved + resolution notes + a `vendor_resolved` activity recording
the vendor); the landlord still owns the final **close**.

## Statement & SLA

- **Statement** (`/v/portal/statement`) — read-only record of vendor-category
  `ticket_costs` (on the vendor's tickets, by `recorded_at`) + the vendor's
  `expenses`, normalised to cents, with a UTF-8-BOM CSV export (CSV-injection
  guarded). **Not a payout** — Stripe Connect (Phase 41/42) is landlord
  settlement; vendor disbursement is out of band.
- **SLA** (`/v/portal/sla`) — resolution-within-SLA % (`resolved_at <=
  resolution_due_at`), breach count, open-overdue (`breachedResolutionSla`),
  resolved total + avg resolution hours, over a 30/90/365-day window.

## Cross-references

- `docs/runbooks/maintenance.md` — landlord-side ticket + vendor assignment
- `docs/runbooks/alert-thresholds.md` — SLA gauges
