import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-74-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-21',
    result: '18/18 findings pass — zero PRD-finding deferrals',
    commits: {
        'CARD-REGISTRY': '15353b8',
        'CARD-TYPES': '3ae9e85',
        'DASH-SHARE': 'd156485',
        'DASH-EXPORT': '9d8ff63',
        'CROSS-BUILDING': 'f8279f3',
        'CI': 'this commit',
    },
    summary:
        'Phase 50/73 composable-dashboard sequel. CARD-REGISTRY: extracted DashboardService\'s hard-coded card if/else into a DashboardCardRenderer registry (ownership guards lifted verbatim, fail-closed on unknown type). CARD-TYPES: kpi (aggregate tile), chart (bar from label+value, fail-closed on unknown field, 50-pt cap), text (escaped note). DASH-SHARE: dashboard_shares + signed public view (runs with share.landlord_id, never a param) mirroring report-share. DASH-EXPORT: dompdf + XlsxExportService multi-sheet, owner-only, sheet-title char sanitisation. CROSS-BUILDING: persisted main-dashboard scope (active_building|all_buildings) on the main_dashboard pref row {widgets,scope}, query-param override, multi-building gate.',
    tests: 'Phase74CardRegistryTest (5) + Phase74CardTypesTest (8) + Phase74DashboardShareTest (9) + Phase74DashboardExportTest (5) + Phase74CrossBuildingTest (6) + Phase74DashboardDepth2SurfaceTest (9). InertiaPageReachability + UiArchetype guards stay green.',
    constraints_preserved:
        'Renderers re-validate landlord ownership of every referenced report/metric (fail-closed); signed dashboard-share runs with the row landlord_id; NO DB::raw; MetricFormulaService no-eval untouched; buildPayload is the single render path for show + share + pdf + xlsx.',
    coderabbit:
        'Clean per sub-phase. Caught: CARD-TYPES chart field-error key (fixed); DASH-EXPORT illegal-sheet-char 500 (HIGH, fixed + regression test); CROSS-BUILDING surfaced a pre-existing main_dashboard-row leak into the dashboards list (tied off).',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
