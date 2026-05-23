import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-96-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-23',
    result: '6/6 findings pass — zero PRD-finding deferrals',
    summary:
        'A water client now has a real dashboard. Each water line (WaterConnection) renders the SAME shared Components/Water/* the Phase-93 tenant self-service uses — consumption history, usage/leak alert, charges — keyed off the connection meter, not a lease. WaterAccountService was generalized: the private reading-query core scopes by an arbitrary column (unit_id | meter_id); the unit-centric public API is retained as thin delegators (Phase-93 byte-identical); a new overviewForConnection(WaterConnection) keys readings by the meter, bounded by connected_at AND landlord_id, with charges empty until Phase 97. DashboardController::waterClientDashboard injects the service and maps each of the client\'s connections to {meta + history/summary/alert/charges/disconnection + effective_rate}; the Vue composes the four shared components per line, with flat-rate/no-meter lines showing a note. effective_rate = client_rate ?? landlord water_client_rate ?? null (never a fabricated default).',
    tests: 'Phase-96 tests: WaterClientDashboard 12 (meter aggregation, connected_at isolation floor, decommissioned-meter no-leak, anomaly alert, disconnection, no-meter graceful, unit-path regression, enriched payload, rate fallback, multi-line) + Surface 3. Phase-93 tenant path green (refactor safe). Full Water suite green. Pint/build(exit 0)/eslint(new files 0)/nav-audit clean; lang parity water 281 (more_soon dropped, +5 client_dash).',
    review: 'Multi-reviewer read-only pass (CodeRabbit + pr-review code-reviewer + silent-failure-hunter). Two cleared it; silent-failure-hunter found + FIXED HIGH: the meter-centric reads were scope-free with no landlord_id backstop and a soft-deleted/foreign meter_id would drive the dashboard (the Phase-93 cross-account class). FIX: overviewForConnection resolves the meter through the soft-delete/tenant-scoped relation (decommissioned/foreign -> empty account), every read bounded by landlord_id, has_meter derived from the scoped relation (agrees with the serial + the empty account), and StoreWaterConnectionRequest meter/unit exists-rules tightened with whereNull(deleted_at). Also dropped the orphaned client_dash.more_soon key.',
    constraints_preserved:
        'Unit-centric Phase-93 API byte-identical (delegators, landlord_id defaults null). Cross-account isolation: readings bounded by connected_at + landlord_id; meter resolved via scoped relation. Honest rate (null shown as "not set", never coerced). charges=[] until Phase 97. Money decimal:2. Shared Components/Water/* reused verbatim (the deliberate Phase-93 seam).',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
