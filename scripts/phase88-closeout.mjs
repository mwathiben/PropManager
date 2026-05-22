import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-88-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-22',
    result: '9/9 findings pass — zero PRD-finding deferrals',
    summary:
        'The water read -> review -> bill cadence + the safety that water revenue is never silently dropped. CYCLE-CONFIG: water_reading_day + water_review_days on payment_configurations + buildings + water_readings.auto_approved; shared settings editor extended. READING-REMINDER: NET-NEW water_reading_due type (3 layers) + water:reading-reminders daily (caretaker reminded on the building reading day, idempotent per building+month). REVIEW-AUTOAPPROVE: NET-NEW water_review_due type + water:review-window daily — nudges the landlord AND auto-approves any reading left pending past the review window (WaterReading::autoApprove + TenantActivity audit + per-landlord escalation) so a pending reading bills instead of hanging forever. RE-READ: WaterReadingController::requestReread reopens a non-invoiced reading + re-prompts the caretaker. CI: behavioral + surface tests + runbook.',
    tests: 'Phase-88 tests: WaterReadingCycle 8 + Surface 5. Full water suite + WaterReadingController 96 regression green. Pint/build/eslint/nav-audit clean.',
    review:
        'Multi-reviewer read-only pass (CodeRabbit + pr-review-toolkit code-reviewer + silent-failure-hunter) caught + fixed: CRITICAL cron loops aborting the whole portfolio on one poison row (now per-building try/catch+Log+continue); HIGH review_days=0 same-day auto-approve over-bill (now min:1); MEDIUM withoutOverlapping on both crons, approve() clears stale auto_approved, requestReread warns when no caretaker; N+1 config memoized. Accepted-as-correct: created_at-vs-reading_date (bills in its own period), soft-deleted units (no bill), plan-gate on cron (don\'t-lose-revenue).',
    constraints_preserved:
        'THE SAFETY: a reading left pending past the window auto-approves so it always bills (the bug = pending readings silently excluded from invoicing + hanging forever). Crons run scope-free (withoutGlobalScope landlord) + explicit landlord_id. Notification types IMPORTANT (email+in-app default-on). water Settings landlord-only (Phase 86). Money decimal:2. Review window measured from reading.created_at (robust to late reads).',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
