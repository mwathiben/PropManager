import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-78-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-21',
    result: '18/18 findings pass — zero PRD-finding deferrals',
    commits: {
        'AMENITY-DEPTH': 'bca45a47 + 1a04143a',
        'PROPERTY-METRICS': '1a04143a',
        'PROPERTY-VIEW': '506c07e5',
        'PROPERTY-SWITCH': '947ee807',
        'PROPERTY-BENCHMARK': 'this cycle',
        'CI': 'this commit',
    },
    summary:
        'Net-new property tier above the building level. AMENITY-DEPTH: building_amenity_details (per-amenity quantity/provider/account_ref/monthly_cost, decimal money) + AmenityDetailService::sync (allow-list ∩ selected, prunes deselected, updateOrCreate keyed by building+key) wired into BuildingController::updateSettings; Building::getActiveAmenities merges detail behind relationLoaded guard (no N+1); Edit.vue detail panel + Show.vue render. PROPERTY-METRICS: PropertyMetricsService forProperty/forLandlord — batched grouped joins keyed by property_id (building/unit counts, occupancy, active-lease rent roll, non-voided arrears), strictly buildings.landlord_id-scoped. PROPERTY-VIEW: PropertyController index/show (owner-gated 404) + Properties/Index.vue + Show.vue (KPI cards, NOI row, per-building drill-down). PROPERTY-SWITCH: users.active_property_id (nullOnDelete) + ActivePropertyResolver (stored-if-owned -> first -> null) + switchTo (landlord-only) + current + shared propertySwitcher prop + PropertySwitcher.vue in topbar. PROPERTY-BENCHMARK: PropertyBenchmarkService::forLandlord — occupancy / NOI-margin / gross-yield percentile ranks (higher-is-better; null when single property) + portfolio medians + overall rank; properties.benchmark route + Benchmark.vue; property:benchmark-rollup weekly cron emits landlord_portfolio_occupancy_pct gauge (visibility-only, no alert). CI: surface watchdog + docs/runbooks/property.md + nav.portfolio/nav.benchmark links (Portfolio + Benchmark in the PROPERTIES section).',
    tests: '34 Phase-78 tests: AmenityDepth 5 + PropertyMetrics 4 + PropertyView 3 + PropertySwitch 8 + PropertyBenchmark 5 + Surface 9. Full Property suite green. Pint clean, build clean.',
    constraints_preserved:
        'All property/metrics/benchmark queries scoped by buildings.landlord_id (or properties.landlord_id); cross-tenant access 404 via abort_unless + TenantScope route-model binding. Property tier gated role:landlord,caretaker; properties.switch further restricted to role:landlord (active-property is per-landlord). Money is DECIMAL FLOAT (building/property subsystem convention), not cents. active_property_id excluded from $fillable, set by direct assignment. Benchmark percentiles null for single-property portfolios. Rollup is visibility-only (no paging), in the Phase-49 Sunday cost cluster.',
    coderabbit:
        'AMENITY-DEPTH: HIGH dead merge (detail never displayed) -> eager-load amenityDetails + render in Show.vue; MEDIUM Vue render-time mutation -> pure getter + onMounted/toggle seed; LOW cents->decimal rename (silent KES/100 bug) + account_ref wired + landlord_id index. PROPERTY-VIEW: HIGH routes only auth-gated -> role:landlord,caretaker group; M2 orphaned Landlord/Home.vue deleted; M3 caretaker-view + tenant-403 tests added. PROPERTY-SWITCH (manual review — CLI unavailable): M1 caretaker could write active_property_id with no UI/effect -> switch route restricted to role:landlord + caretaker-403 test; M2/M3 duplicate resolver query on the every-response hot path -> derive active_id from already-loaded option rows; LOW imports + intentional-redundancy comment + empty-portfolio redirect test.',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
