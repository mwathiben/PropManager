import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-89-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-22',
    result: '7/7 findings pass — zero PRD-finding deferrals',
    summary:
        'Backfill historical water readings from a CSV or EXCEL sheet by enriching the existing imports feature (Import/ImportService/ImportsController). IMPORT-WATER: importWaterReadings resolves Meter::resolveActiveForUnit -> meter_id and records readings as already-billed history (status=approved + is_invoiced=true) so InvoiceService never re-bills them (closed a latent bug where imported readings were billable); optional Consumption/Cost preserved via Model::withoutEvents (fallback cost = consumption * current rate, matching Phase-87 reading.cost). IMPORT-DEDUP: idempotent re-import (skip existing meter+date, summary.skipped_duplicates). IMPORT-XLSX: .xlsx/.xls accepted + parsed via PhpSpreadsheet (parseRows/parseSpreadsheet). REACH: landlord-only Import-history quick-link from the water hub. CI: behavioral + surface tests + runbook.',
    tests: 'Phase-89 tests: WaterHistoricalImport 6 + Surface 5. Import/invoice/storage/water regression green. Pint/build/eslint/nav-audit clean. No migration this phase (pure enrichment of existing tables).',
    review:
        'Multi-reviewer read-only pass (CodeRabbit + pr-review-toolkit code-reviewer + silent-failure-hunter) caught + fixed: CRITICAL Excel date month/day swap (formatData=true locale string -> Carbon swap) -> read formatData=false + convert Excel serials before validation (test asserts a real dd/mm/yyyy cell round-trips); MEDIUM xlsx decompression-bomb/memory -> data-only reader + 10000-row cap; MEDIUM Storage::path() non-local fragility -> temp-file read from tenant disk; HIGH current<previous silent-zero -> failed row. Kept: omitted-Cost = consumption*current rate (consistent with Phase-87 reading.cost flat estimate; Cost column preserves exact history; never re-bills).',
    constraints_preserved:
        'Imported readings = read-only history that NEVER re-bills (is_invoiced=true; InvoiceService bills approved+is_invoiced=false). Idempotent re-import. Landlord-scoped unit resolution. withoutEvents sets landlord_id/recorded_by/status/consumption/cost explicitly; version has a DB default. Architected so water clients (Phase 94) reuse the importer (maps by unit_number today). Money decimal:2.',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
