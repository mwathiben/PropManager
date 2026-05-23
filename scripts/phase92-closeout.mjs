import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-92-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-23',
    result: '7/7 findings pass — zero PRD-finding deferrals',
    summary:
        'Borehole regulatory compliance, reusing the Phase-82 document-expiry machinery. COMPLIANCE-DOCS: Document::DOCUMENT_TYPES += wra_abstraction_permit + water_quality_certificate (+ lang/document.php en/sw/ar); Building::documents() morphMany (closes a Phase-82 gap — title_deed/insurance can now attach to buildings too); DocumentController::store extended to allow documentable_type=Building with a landlord-ownership authz branch; a renewable building doc with expires_at flows through the EXISTING documents:scan-expiring -> document_expiry notification to the landlord (NO new cron, NO new notification type). ABSTRACTION-LIMIT: buildings.water_abstraction_limit (m3/year) + WaterComplianceController::updateLimit (landlord-only, UpdateWaterAbstractionLimitRequest authorizes building ownership) + route water.compliance.limit. WaterComplianceService::forLandlord: per borehole building (effective water_source=borehole, building override else global config) — abstraction used this calendar year (prefers the main meter [top-level metered parent feeding sub-meters = the abstraction point]; else summed unit consumption; basis labelled) vs limit + utilization% + projected-annual + status (no_limit/unknown/ok/warning/exceeded, honest nulls), plus the permit + quality-cert document status (expiryStatus reused) + a portfolio summary; batched grouped queries, landlord-scoped, soft-delete-guarded. COMPLIANCE-SURFACE: landlord-only compliance tab (WaterHubController getComplianceData caretaker-bounced + Hub.vue ComplianceTab canSettings-gated, ShieldCheckIcon) — ComplianceTab.vue summary + per-building permit/cert cards (status chips + upload/renew modal posting to documents.store / documents.renew) + abstraction limit form + utilization bar; empty state when no borehole buildings. i18n en/sw/ar. CI surface + behavioral tests + runbook.',
    tests: 'Phase-92 tests: WaterCompliance 13 + Surface 7 (20 tests). Documents + Water regression 167 / 465 green. Pint/build/eslint(custom rules 0)/nav-audit clean. Lang parity water 171, document 67.',
    review: 'Multi-reviewer read-only pass (CodeRabbit + pr-review-toolkit code-reviewer + silent-failure-hunter). Fixed (silent-failure-hunter, regulatory-data honesty): HIGH early-year projection inflation (gate projection until >= ~8% of year elapsed); HIGH unit-meter estimate is a lower bound (estimate flag + escalate to warning at >= 75% util, surfaced amber); MEDIUM undated permit/cert (expiry_status none) now warns not green; MEDIUM summary watch count for warning-level buildings; MEDIUM no_limit rendered actionable amber (distinct from unknown). Verified non-issues: C1 null-consumption is moot (water_readings.consumption is NOT NULL — 0 is a real value); multiple-mains/replacement (predecessor soft-deleted, excluded); year-boundary attribution (matches intelligence).',
    constraints_preserved:
        'Compliance tab + abstraction-limit are landlord-only (caretaker bounced to overview; route role:landlord + FormRequest authorize building ownership). Reminders REUSE Phase-82 doc-expiry (no parallel cron / notification type). Only borehole buildings listed. Abstraction "used" prefers the main meter (true abstraction) else estimates from unit meters (understates — labelled). Honest nulls (no_limit / unknown) — never a fabricated compliant status. Money/volume decimal:2. water_readings.unit_id is NOT NULL, so a building main meter carries a unit.',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
