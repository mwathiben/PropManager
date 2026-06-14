# Tooling / Stack Decisions — PropManager

Evaluation of candidate free/open-source self-hosted tools, scored against
PropManager's **actual** architecture (Laravel 12 + Inertia/Vue 3, MySQL,
multi-tenant `TenantScope`, Kenya) and existing code — not in the abstract.
Companion to [`payments-hub-rebuild-foundation.md`](payments-hub-rebuild-foundation.md)
and [`legal-compliance-kenya.md`](legal-compliance-kenya.md).

> Guiding principle: prefer reusing the shapes PropManager already has over
> bolting on a second system with its own tenancy/auth/data model. A tool is
> worth adopting only when it delivers a capability we genuinely lack and cannot
> build proportionately in-stack.

## Verdicts at a glance

| Tool | Verdict | One-line reason |
|---|---|---|
| **Documenso** | ✅ Adopt | Genuine new capability — certificate-backed (PKCS#12) signed PDFs for agreements. |
| **WhatsApp (OpenWA)** | ❌ Reject tool · ✅ do the need properly | Unofficial automation = ToS breach + ban risk; the official BSP path is already ~70% built. |
| **Immich** | ❌ Skip | Per-user (no tenant) model fights `TenantScope`; ML over IDs is a DPA liability. |
| **Anytype** | ❌ Skip | Local-first, E2E-encrypted, single-account, localhost-only — the inverse of a queryable multi-tenant SaaS. |
| **Twenty CRM** | ⚠️ Build native | We already own 80%; the gap is a pipeline stage, not a second app. |
| **Papermark** | ❌ Skip | View-tracking ≠ Kenyan proof-of-service; headline features are commercially licensed. |

---

## ✅ Documenso — adopt (e-signature for agreements)

The one clear adoption: a tamper-evident, **PKCS#12 certificate-backed** signed-PDF
engine — the integrity anchor of the Agreements layer that we don't otherwise have.

- **License:** AGPL-3.0. Run it **unmodified, as a separate self-hosted service**
  (its own Postgres) that PropManager calls over the network → the copyleft does not
  reach PropManager's code. Do not fork/patch the image.
- **Integration (v2 "envelope" API + webhooks):** render the agreement PDF → create
  envelope (PDF + recipients + fields) → distribute → **embedded signing inside our
  own Vue UI** (no redirect) → verify the signed webhook on `DOCUMENT_COMPLETED` →
  download the signed PDF + signing certificate → store to private storage against the
  agreement.
- **We supply the `.p12`** signing certificate (signing fails without it).
- **Kenya limit (honest):** Documenso is **not** a CA-Kenya-licensed CSP, so its
  signature is a strong *simple* electronic signature — the **Track A** integrity
  layer (management agreements + ≤2-yr tenancies). It does **not** satisfy **Track B**
  registrable-lease (>2 yr) formalities. See `legal-compliance-kenya.md` §4.
- **Picked over DocuSeal** for the cryptographic rigor (DocuSeal is lighter / SQLite /
  built-in SMS, but our OTP layer already covers phone-based identity).
- **Dev (Windows/Laragon):** run Documenso + Postgres in Docker Desktop/WSL2, not native.

## ✅ WhatsApp — reject OpenWA, finish the official path (already started)

The *need* (WhatsApp is the dominant channel in Kenya for reminders, receipts, notices)
is real and high-value. **OpenWA is the wrong vehicle** — it drives WhatsApp Web
unofficially, violating WhatsApp's ToS with a high number-ban rate; for a multi-tenant
payments/legal-notice product that's a catastrophic, self-inflicted single point of
failure.

The compliant path — the **official WhatsApp Business Cloud API via a BSP** — is
**already ~70% built**: `ChannelTransport::sendWhatsApp()` (Twilio BSP +
`ContentSid`/`ContentVariables`), `WhatsAppTemplateService` (Meta-approved templates,
per-landlord), `NotificationPreference.whatsapp_number`, and WhatsApp already in
`NotificationService` urgency channels. **Harden, don't replace:**
1. Explicit, timestamped **opt-in consent** record (Meta requirement + Kenya DPA).
2. **Fail-closed** the plain-text fallback — Meta rejects business-initiated free text
   outside the 24-h window, so fall back to SMS rather than send non-compliantly.
3. Ingest **delivery/read webhooks** → doubles as **proof of service** (Civil Procedure
   Rules Order 5 r22C: service deemed effected on the delivery receipt).
- Register the mapped templates as **Utility** category (free/cheap tier). BSP options:
  Twilio (wired), Africa's Talking (consolidates with existing SMS), 360dialog (cost).

## ⚠️ Twenty CRM — build native CRM-lite instead

PropManager already owns the owner-management domain: `manager`/`owner` roles,
`PropertyOwner` contact + management-fee + `OwnerPayout`, owner portal,
`TenantScope`/`Auditable`. The only real gap is a **pipeline stage + activity
timeline** (no Lead/Pipeline/stage exists today). Integrating Twenty would mean a
second full app (TS/NestJS/Postgres/Redis) with workspace-per-schema tenancy and its
own OAuth to reconcile against `landlord_id` row-scoping + Sanctum — data duplication,
sync, double auth, AGPL-on-fork, no clean embed. **Build native:** a `stage`/`status` +
note/activity timeline on `PropertyOwner` (reuse `TenantNote`/`LandlordTask`) for
owner acquisition, and a `RentalApplication` lead model feeding lease creation for
prospective tenants. Revisit Twenty only if a manager outgrows the lite layer (then
API-sync, not a core dependency).

## ❌ Immich — skip (use a Laravel media layer)

Excellent personal photo server, wrong category here. Its ownership model is **per
user with no tenant layer** (an API key acts *as* one user), which fights PropManager's
central `TenantScope` authorization; its face-recognition/ML over tenant IDs and people
is a **Kenya DPA biometric liability**; and it's heavy (Postgres + Redis + ML, ~6–8 GB
RAM) and AGPL. PropManager already has a polymorphic tenant-scoped `Document` model and
a move-out inspection (condition-report) flow. **Instead:** keep S3/private storage +
add `spatie/laravel-medialibrary` (or `intervention/image`) for conversions, **strip
EXIF/GPS** on upload, serve via signed short-lived URLs; optional stateless `imgproxy`
sidecar for on-the-fly resizing.

## ❌ Anytype — skip (no SaaS-backend fit)

Local-first, P2P, **end-to-end encrypted**, single-account, with a **localhost-only**
API that requires the desktop app running. The server literally cannot read the
encrypted data, so cross-tenant queries/reporting are impossible by design — the exact
inverse of what a server-controlled multi-tenant SaaS needs. App layer is
source-available (not OSI). No product fit; an internal team wiki (if ever needed) is
better served by Outline/BookStack or the existing stack.

## ❌ Papermark — skip (build the sliver natively if ever needed)

View-tracking does **not** satisfy Kenyan **proof of service**: the legal test is
provable **delivery** (affidavit + delivery receipt; Order 5 r22B/22C; Evidence Act
s106B), not that the recipient *opened* the document — and framing service around
"opened" invites a harmful "not opened = not served" inference. Existing Africa's
Talking SMS + mail delivery receipts + `Auditable` already produce the right artifact.
Its headline features (data rooms, webhooks) are **commercially licensed** (the `/ee`
tree), not free. It is **not** interchangeable with Documenso (share-track vs sign). If
tracked brochure/statement sharing is ever wanted, build a native signed-link +
view-audit log on the existing `Document`/`Auditable` stack.

---

## Roadmap placement

| Decision | Where it lands |
|---|---|
| Documenso | e-sign **layer 3** in the agreements work (L0 primitive → L2 management agreement) |
| WhatsApp hardening | near-term notification-system PR (proof-of-service ties to L3 tenancy notices) |
| Media library (vs Immich) | L3 — move-out condition photos / deposit evidence |
| CRM-lite (vs Twenty) | later manager-tooling phase |
| Anytype / Papermark | not adopted |

**Net:** one adopt (Documenso), one do-properly (official WhatsApp, mostly built),
four skip/build-native — because PropManager already has the right shapes, and most of
these tools would add a second system to reconcile rather than fill a real gap.
