import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-100-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-24',
    result: '3/3 findings pass — zero deferrals. First Tier-7 merit-pick after the water domain: reporting depth for the landlord/PM audience.',
    summary:
        'Three reports the mature hub lacked, all wired onto the LIVE FinanceReportController (Finances reports tab) — the legacy ReportsController is dead/unrouted and was left untouched. (1) Rent-roll snapshot: RentRollService (per-unit tenant/rent/deposit/outstanding[batched]/lease-window/status occupied|vacant|expiring) → finances.reports.rent-roll pdf/xlsx/csv + a Rent Roll download button. (2) Per-property P&L: PropertyPnlService (cash collected vs expenses by property + net/margin) → finances.reports.property-pnl + a Property P&L button honouring the period filter. (3) Owner statement: OwnerStatementService (per-property collected + expense-by-category + net to owner, 404 on a foreign property) → finances.reports.owner-statement PDF + an Owner Statement button on the property detail page. Money decimal:2; landlord-scoped throughout; no new migration.',
    review: 'Multi-reviewer read-only pass (CodeRabbit + pr-review code-reviewer + silent-failure-hunter); findings applied. A scout-phase discovery (recorded mid-build): the obvious ReportsController is dead/unrouted — the live reports surface is FinanceReportController; the rent-roll was first wired there by mistake and reverted via git before re-wiring onto the live controller.',
    tests: 'Phase100ReportsDepthTest: rent-roll (occupied/vacant/expiring + financial position + landlord scoping + pdf/xlsx/csv endpoint + tenant-forbidden), per-property P&L (collected/expenses/net + scoping + endpoint), owner statement (totals + expense-by-category + pdf endpoint + 404 on a foreign property). Pint clean; eslint 0 errors (baseline drift pre-existing, confirmed on clean HEAD); nav-audit + imports clean; vite build; lang parity en/sw/ar (property.show.owner_statement). Code-only — no migration.',
    constraints_preserved:
        'All three reports are landlord-scoped (building.landlord_id / property.landlord_id); the owner statement 404s on a property that is not the landlord\'s. Cash-basis P&L (payments, not invoiced). Reused the proven export pattern (Maatwebsite xlsx + dompdf blade + csv) sourced from a single service so pdf/xlsx/csv/on-screen never drift. The dead ReportsController was NOT extended. Deferred: notification-preference gap (later comms-depth cycle); the full owner/PM-company role + payouts epic (owner statement here is a report only).',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
