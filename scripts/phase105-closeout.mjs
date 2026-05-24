import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-105-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-24',
    result: '3/3 findings pass — zero deferrals. User-directed portfolio-first landlord landing; resolves the Phase-55 default-scope drift.',
    summary:
        "The landlord /dashboard landing is now a cross-property PORTFOLIO overview, and the rich building dashboard is the drill-down. DashboardController::landlordDashboard branches on building_id: absent → Portfolio/Home (getPortfolioOverview), present (incl the 'all' sentinel) → the EXISTING building dashboard (getLandlordDashboardData + Dashboard.vue body unchanged). getPortfolioOverview reuses PropertyMetricsService::forLandlord (no N+1) for portfolio KPIs (unit-weighted occupancy, summed rent-roll/arrears, counts) + per-property at-risk-sorted cards each carrying a primary_building_id for one-click drill + a landlord-scoped action summary. Portfolio/Home.vue: MetricCard KPIs + needs-attention chips + per-property cards (drill to the building dashboard, fallback properties.show) + empty-state + h1. setScope reworked to reload into the building view (all_buildings → 'all' sentinel). Lang en/sw/ar.",
    review: 'Full 3-agent multi-reviewer pass (CodeRabbit + silent-failure-hunter + pr-review code-reviewer) — all executed for real (29/33/21 tool-uses, cited lines). Convergent verdict: building-dashboard NON-REGRESSION confirmed (getLandlordDashboardData unchanged; only Dashboard.vue deltas are setScope + an aria-label→sr-only a11y fix), aggregation correct (unit-weighted occupancy, div-by-zero guarded, no double-count), drill-down landlord-scoped (own buildings only; primary_building_id == the building view\'s main-building set, verified across all building-creation paths), i18n parity (portfolio.php 22 keys ×3 + dashboard.clear_building_filter ×3), tests reconciled meaningfully not deleted-to-pass. FIXED the one actionable MEDIUM: two in-building "Vacant Units" links used route(dashboard,{status:vacant}) with no building_id → would bounce to the portfolio → now pass property_id+building_id (status was already a server-side no-op, so low blast radius). Accepted: a property-with-no-buildings card drills to properties.show (graceful, intentional — more forgiving than the building view\'s onboarding gate); a few unused-but-consistent lang keys.',
    tests: 'Phase105PortfolioHomeTest 7 (landing renders Portfolio/Home + has kpis/actions/properties; KPIs aggregate across the landlord\'s properties; portfolio excludes other landlords\' properties; building_id drills into Dashboard; no-properties → onboarding redirect; primary_building_id resolution). Phase55DashboardFiltersTest + Phase74CrossBuildingTest reconciled to portfolio-first (landing=Portfolio/Home; building view via building_id; \'all\' sentinel → allBuildingsMode; scope persists). Full tests/Feature/Dashboard dir 39 passed/128 assertions (no regression). Gates: Pint, eslint 0 errors, nav-audit clean, check-import-case/exists OK, vite build exit 0, lang parity (portfolio.php 22 ×3, dashboard.php +1 ×3; 45 lang files per locale). No migration.',
    constraints_preserved:
        'The building dashboard (getLandlordDashboardData + Dashboard.vue render body) is UNCHANGED — zero risk to the 1400-line screen; it is the drill-down, reached with a building_id. Portfolio aggregation is unit-weighted + landlord-scoped (no cross-tenant leak). Drill links target only the landlord\'s own properties/buildings; the building view re-scopes regardless. The Phase-74 scope mechanism is preserved (persist + the \'all\' sentinel drives the aggregate; a specific building_id always scopes to one building). Onboarding redirect kept for property-less landlords. Phase-23 a11y (h1) + i18n (no new hardcoded English) maintained. Deferred: deeper portfolio analytics (benchmark medians/percentiles already exist in PropertyBenchmarkService — could enrich the overview later); making the dashboard ?status= filter actually consumed server-side (pre-existing no-op, out of scope).',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
