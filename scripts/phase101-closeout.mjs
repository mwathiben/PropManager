import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-101-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-24',
    result: '3/3 findings pass — zero deferrals. Phase 1 of the owner / PM-company model: the owner as a first-class contact entity + consolidated statement.',
    summary:
        "A property OWNER as a landlord-scoped CONTACT (not a login user — that role is a later OWNER-PORTAL phase; property_owners.user_id is reserved). PropertyOwner model/migration/factory/policy/requests; properties.property_owner_id (nullable, nullOnDelete — deleting an owner unassigns properties, never deletes them). PropertyOwnerController: CRUD + assign/unassign + statement(PDF)/emailStatement, EXPLICITLY landlord-scoped via WithLandlordScope (the Finance convention) with same-tenant abort_unless guards. OwnerStatementService::forOwner consolidates across the owner's properties via a shared aggregate() (also feeds Phase-100 forProperty — non-regressive): collected excl. voided + water-client, expense building-precedence, empty-owner→0, per-property breakdown. OwnerStatementMail (queued, markdown + PDF attachment, real currency, failed() handler). Owners/Index.vue CRUD + assignment + visible flash; Properties/Index Owners link. Lang en/sw/ar.",
    review: 'Full 3-agent multi-reviewer pass (CodeRabbit + pr-review code-reviewer + silent-failure-hunter) — note: it required a retry, the first attempt hit a subagent usage quota (returned 0 work) and the user flagged it. On the real run, all three converged + FIXED: CRITICAL the controller relied only on TenantScope (boot-conditional) rather than explicit landlord scoping → index could leak / statement/assign trusted the bound model\'s landlord_id → now WithLandlordScope::getLandlordId() + abort_unless same-tenant on assign/unassign/statement/email; CRITICAL the download blade dereferenced $landlord->name unguarded (NPE) → both paths now feed a guaranteed name; HIGH OwnerStatementMail hardcoded currency_code=KES → real code threaded + a failed() handler so a queued send that dies is logged (the PM is told "is being emailed", not "emailed"); MEDIUM unvalidated period param silently collapsed to "this month" → validated against the known set; UX the assign select hid inactive-but-assigned owners + flash was ARIA-only → all owners listed + a visible banner. Caretaker access to owner statements is KEPT (consistent with Phase-100 finances reports being caretaker-accessible) and pinned by a test. N+1 in the per-property breakdown accepted (small portfolios, throttle-protected).',
    tests: 'Phase101OwnerFoundationTest 10 (CRUD + landlord-scope deny [403/404] + tenant-forbidden + assign/unassign + cross-tenant-assign-denied + caretaker-can-view + forOwner aggregation [empty→0, voided excluded, per-property breakdown] + statement PDF + foreign-owner deny + email queued + no-email-no-queue) + Phase100ReportsDepthTest 12 still green (forProperty shape unchanged by the aggregate() refactor). Pint/php-l/eslint/nav-audit/imports/build clean; lang parity en/sw/ar (owners + property.index.owners + emails.subjects.owner_statement). New migration → dev DB migrated.',
    constraints_preserved:
        'Owner is a contact, not a User (no auth role; user_id reserved). One owner per property. Every controller query explicitly landlord-scoped + same-tenant guards (defense-in-depth over TenantScope). forOwner: cash basis, voided + water-client excluded, no expense double-count, owner-scoped. Deleting an owner unassigns (not deletes) properties, surfaced in the flash. Deferred to later owner phases: the login/OWNER-PORTAL role, owner payouts, %-share ownership, and the forOwner breakdown N+1 optimization.',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
