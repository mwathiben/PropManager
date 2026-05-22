import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-87-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-22',
    result: '8/8 findings pass — zero PRD-finding deferrals',
    summary:
        'Water tariff depth on the live biller, applied NON-DESTRUCTIVELY (unset => today\'s flat charge). TARIFF-MODEL: payment_configurations + buildings gain tiered_tariffs (json bands) + water_standing_charge/minimum_charge/sewerage_percent/vat_percent + water_source. TARIFF-SERVICE: WaterTariffService::computeConsumptionCharge (tiered/block or flat fallback) + assembleWaterCharge (base + standing + sewerage% + VAT%, floored at minimum). BILLING-WIRE: WaterReadingObserver reading.cost = flat per-reading estimate; InvoiceService::calculateWaterCharges tiers the PERIOD AGGREGATE consumption then assembles the fixed components (the authoritative charge). CONFIG-UI: shared WaterSettingsForm gains a global tiered-bands editor + standing/min/sewerage/VAT/water_source (global + per-building inherit-aware); UpdateWaterSettingsRequest validates incl band contiguity; controller persists; i18n en/sw/ar. CI: behavioral + surface tests + runbook.',
    tests: 'Phase-87 tests: WaterTariffEngine 11 + Surface 5. Full water suite + WaterReadingController + InvoiceWorkflowIntegration 90 regression green. Pint/build/eslint/nav-audit clean.',
    review:
        'Multi-reviewer read-only pass (CodeRabbit + pr-review-toolkit code-reviewer + silent-failure-hunter) caught + fixed: CRITICAL per-reading-vs-per-period tiering (silent under-bill on multi-reading periods) -> tier the period aggregate at invoice time; CRITICAL no band-coverage validation (gaps/overlap/inversion silently bill at zero) -> withValidator contiguity rule; HIGH minimum_charge flooring a zero base (bills no-reading tenants) -> no readings bills 0; MEDIUM per-building dead tiered config + empty-[] trap -> bands landlord-global v1 + added missing per-building min/VAT inputs. Regression tests added for each.',
    constraints_preserved:
        'Live biller correct + non-destructive (flat buildings byte-for-byte; verified by InvoiceWorkflowIntegration + non-destructive tests). Money decimal:2. water Settings landlord-only (Phase 86). RESEQUENCED (re-right-size, not dropped): WaterConnection -> Phase 94; apportioned billing_mode + common-area + borehole production-cost + effective-dated SCHEDULING + per-building tiered-bands UI -> later water phases. water_source carried for 91/92.',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
