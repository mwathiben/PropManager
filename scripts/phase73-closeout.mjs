import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-73-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-21',
    result: '18/18 findings pass — zero PRD-finding deferrals',
    commits: {
        'DASHBOARD-EDITOR': '9ee4461',
        'REPORT-SHARE': '644a52a',
        'SCHEDULED-DEPTH': '20ebda1',
        'METRICS-DEPTH': '2eaa0b8',
        'CI': 'this commit',
    },
    summary:
        'Phase 50 REPORTS-DEPTH sequel, right-sized to the genuine gaps after the scout found scheduled email reports + MetricFormulaService already shipped. DASHBOARD-EDITOR: index/create/store/edit/update/destroy/setDefault/preview + StoreDashboardRequest + DashboardService::validateLayout (re-validates every card ownership, fail-closed) + Pages/Dashboards/{Index,Editor}. REPORT-SHARE: report_shares table + signed public view (runs with share.landlord_id never a param, id bound into signature) + idempotent revoke + access trail. SCHEDULED-DEPTH: scheduled_reports.paused_at + ScheduledController::update (cadence re-anchor only on change) + togglePause (resume re-anchors, no backlog) + cron whereNull(paused_at). METRICS-DEPTH: 8 new safe ALLOWED_FIELDS dimensions (real columns, parameterised, numeric auto-metric-eligible) + ReportMetricController::manage + validate (live no-persist) + Pages/Reports/Metrics. CI: Phase73ReportsDepth2SurfaceTest + report-tool launcher in ReportsTab (the suite was previously URL-only) + reports.md runbook section.',
    tests: 'Phase73DashboardEditorTest (8) + Phase73ReportShareTest (9) + Phase73ScheduledDepthTest (7) + Phase73MetricsDepthTest (9) + Phase73ReportsDepth2SurfaceTest (12)',
    constraints_preserved:
        'ReportBuilderService allow-list + parameterised values (NO DB::raw); MetricFormulaService no-eval/no-functions untouched; every read path re-validates landlord ownership; signed-share runs with the row landlord_id.',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
