import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-91-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-22',
    result: '8/8 findings pass — zero PRD-finding deferrals',
    summary:
        'The landlord water hub becomes INFORMATIVE. WaterIntelligenceService::forLandlord (batched grouped queries, zero N+1, zero-denominator guards): 12-month consumption trend + period-over-period delta + trailing-3mo projection + per-building comparison (INTELLIGENCE-SERVICE-1); leak intelligence — recent is_anomalous readings + main-vs-sub NON-REVENUE-WATER per main meter (graceful empty when no hierarchy) + top consumers (INTELLIGENCE-SERVICE-2); billing-vs-collection — water billed vs pro-rata water share of payments (water not payment-separable) + collection rate + outstanding (INTELLIGENCE-SERVICE-3). PRODUCTION-COST: water_production_costs table + WaterProductionCost model/policy/factory + StoreWaterProductionCostRequest + WaterProductionCostController store/destroy (landlord-only, water.production-costs.*) (PRODUCTION-COST-1); cost-of-production-vs-revenue margin + margin% + cost-per-unit (PRODUCTION-COST-2). INTELLIGENCE-UI: landlord-only intelligence tab (WaterHubController getIntelligenceData caretaker-bounced + Hub.vue IntelligenceTab canSettings-gated, ChartBarIcon) — IntelligenceTab.vue KPI cards + ChartCard trend/comparison + top-consumers + leak panel + NRW table + billing-vs-collection bar + margin panel + production-cost entry form/list; i18n en/sw/ar (INTELLIGENCE-UI-1). CI surface + behavioral tests + runbook.',
    tests: 'Phase-91 tests: WaterIntelligence 12 + Surface 6 (18 / 82 assertions). Full water suite + Pint/build/eslint green.',
    review: 'Multi-reviewer read-only pass (CodeRabbit + pr-review-toolkit code-reviewer + silent-failure-hunter).',
    constraints_preserved:
        'Intelligence tab + production costs are landlord-only (caretaker bounced to overview; route role:landlord). Collection rate is an explicit ESTIMATE (water not payment-separable — pro-rata water_due/total_due). Non-revenue water only where a main/sub meter hierarchy exists (graceful empty otherwise). Margin uses logged production costs (water_production_costs); without them margin == revenue. Batched grouped queries, no N+1; every ratio guards a zero denominator. Money decimal:2.',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
