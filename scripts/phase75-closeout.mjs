import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-75-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-21',
    result: '18/18 findings pass — zero PRD-finding deferrals',
    commits: {
        'VENDOR-PERF': 'd2a9a8e',
        'VENDOR-ROUTING': 'b4e00ec',
        'PARTS-PRICING': 'f3669f1',
        'PARTS-PREDICT': 'bd3b862',
        'PHOTO-ROLLUP': '5fa4556',
        'CI': 'this commit',
    },
    summary:
        'Phase 49/54 maintenance-workflow sequel. VENDOR-PERF: VendorPerformanceService::forLandlord (within-SLA %, avg resolution, open overdue, cost/ticket, soft-delete-aware cost join) + sortable /maintenance/vendor-performance. VENDOR-ROUTING: vendor_specialties (allow-list gated to Ticket::issueSubcategories) + suggestPool (specialty filter → performance rank) + opt-in autoAssign (config maintenance.auto_route_vendors, never overrides, TicketObserver afterCommit). PARTS-PRICING: append-only part_price_history (PartObserver on create + cost change only) + part_suppliers (unique part+vendor, landlord-scoped FKs) + cheapest/fastest helpers + /parts/pricing UI (sparkline + supplier comparison). PARTS-PREDICT: PartUsageService dailyRate/dailyRatesFor + effective threshold reorder_threshold+ceil(lead_time*rate); trigger_reason (static|lead_time_buffer) + projected_stockout_at on draft lines; parts_usage_rate_per_day + parts_predicted_stockout_count gauges; coarse DB pre-filter bounds cron memory. PHOTO-ROLLUP: landlord-scoped MaintenancePhotoGalleryController (whereHasMorph ticket filters, originals-only with grouped annotation siblings, no N+1) + grid/lightbox PhotoGallery.vue + dompdf export (capped 60, owner-disk base64, throttle:pdf-render).',
    tests: 'Phase75VendorPerformanceTest + Phase75VendorRoutingTest + Phase75PartsPricingTest (4) + Phase75PartsPredictTest (5) + Phase75PhotoRollupTest (6) + Phase75MaintenanceDepth3SurfaceTest (11). Existing Phase49/Phase54 maintenance suites stay green (no reorder-cron regression).',
    constraints_preserved:
        'Every Part/PartSupplier/PartPriceHistory/Vendor/Document/Ticket query landlord-scoped (TenantScope + explicit where); PartSupplierController rejects cross-tenant vendors AND foreign parts; photo gallery + PDF expose only the acting landlord (whereHasMorph runs against the landlord-scoped Ticket subquery); PDF reads only the owner tenant disk; documents.view re-checks ownership; no DB::raw (selectRaw is static SQL only, mirrors VendorPerformanceService); reorder-cron idempotent upsert preserved.',
    coderabbit:
        'Clean per sub-phase. Caught/fixed: VENDOR-PERF cost join bypassed SoftDeletes; VENDOR-ROUTING FinanceFilterService::getVendors silently wiped specialties on edit (HIGH); PARTS-PRICING signed-int overflow on cents columns (widened to unsignedBigInteger + validation max) + untranslated flash; PARTS-PREDICT applied LOW-1 coarse pre-filter to bound cron memory; PHOTO-ROLLUP applied LOW-1 (category filter spans issue + complaint subcategories). Cross-tenant/IDOR, Blade XSS, owner-only PDF reads all verified test-backed.',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
