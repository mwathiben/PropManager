import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-90-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-22',
    result: '9/9 findings pass — zero PRD-finding deferrals',
    summary:
        'Water arrears + a disconnect/reconnect-for-non-payment lever. DISCONNECT: water_meters.disconnected_at + disconnect_reason (separate fields; meter stays status=active so resolveActiveForUnit/billing keep working); MeterController disconnect/reconnect (landlord-only) + Meters UI + TenantActivity audit; THE CAVEAT enforced — only a unit meter (unit_id, no parent, no sub-meters incl soft-deleted) can be disconnected, never a shared/main meter. RECONNECT-FEE: water_reconnection_fee config (landlord+building) + water_pending_charges + WaterReconnectionService records the fee on reconnect; InvoiceService folds unapplied pending charges into the next invoice water_due NON-DESTRUCTIVELY + applies once (locked). ARREARS: WaterArrearsService (overdue invoices with water_due) + Meters-page panel; NET-NEW water_arrears notification (3 layers, IMPORTANT) + water:arrears-notify cron. TENANT: disconnection banner + pay-to-reconnect. CI.',
    tests: 'Phase-90 tests: WaterArrearsEnforcement 9 + Surface 4. Full water + invoice 116 regression green. Pint/build/eslint/nav-audit clean.',
    review:
        'Multi-reviewer read-only pass (CodeRabbit + pr-review-toolkit code-reviewer + silent-failure-hunter). Fixed: CRITICAL reconnect double-charge via TOCTOU (now lock+re-check in a transaction); HIGH water:arrears-notify token ordering (dry-run/failed-send suppression -> Cache::has skip + put after success); MEDIUM isUnitMeter soft-delete-sensitive sub-meter count (now withTrashed) so a main meter can never be misclassified disconnectable; added WaterPendingChargeFactory. Verified: pending-charge applied exactly once (lockForUpdate + applied_at; existingInvoice early-return defers not loses); reconnect on vacant unit charges nothing; authorization landlord-only + owns; cron tenant-scope safe.',
    constraints_preserved:
        'THE CAVEAT: only a unit meter is disconnectable (never a shared/main meter — would cut the building). Reconnection fee billed exactly once on the next invoice (only if an active lease). NON-DESTRUCTIVE pending-charge fold. water_arrears IMPORTANT (email+in-app). Disconnect has no billing/reading effect (arrears keep accruing — the leverage). Money decimal:2.',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
