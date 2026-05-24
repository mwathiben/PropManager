import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-97-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-24',
    result: '6/6 findings pass — zero deferrals. FINAL water-domain phase: 86-97 COMPLETE.',
    summary:
        'Water clients are now fully billable. A NEW water_client_charges table (lease-free; the invoices table is lease-coupled) holds a charge per connection per period. WaterClientBillingService bills metered lines from their meter readings (bounded by connected_at + landlord_id) and flat-rate lines at the fixed rate, both at the effective rate (connection.client_rate ?? landlord water_client_rate) via the Phase-87 WaterTariffService, idempotent per period. The water:bill-clients cron (monthlyOn the 2nd, the completed month) bills active connections with per-landlord isolation and notifies onboarded clients (water_bill_due, the 3-layer recipe). The dashboard charges (Phase-96 WaterChargesCard) populate via WaterAccountService::chargeHistoryForConnection; a water-client finances page (route water-client.finances) shows charges + outstanding + how-to-pay (the dashboard banner payUrl + a nav link); the landlord records payments (record-payment endpoint -> applyPayment oldest-first, transactional, overpayment surfaced) from the Clients tab.',
    guards:
        'THE TWO DEFERRED GUARDS hold: no effective rate (a non-positive rate counts as unset) -> refuse (skipped no_rate); metered-without-readable-meter -> refuse (skipped metered_no_meter). Neither ever coerces a 0 charge. Misconfigured lines surface on the Clients tab (billing_issue chip), not just the log.',
    tests: 'Phase-97 tests: WaterClientBilling 16 (metered/flat/fallback, idempotency, BOTH guards + the 0-rate-is-no-rate edge, no-consumption skip, record-payment oldest-first + endpoint + overpayment-surfaced + cross-landlord, dashboard charges, finances, command+notification) + Surface 6. Full Water suite 233 green. Pint/build(exit 0)/eslint(0 errors)/nav-audit clean; lang parity water 300. Dev DB migrated.',
    review: 'Multi-reviewer read-only pass (CodeRabbit + pr-review code-reviewer + silent-failure-hunter). Found + FIXED: CRITICAL a stored 0 rate bypassed the no-rate guard -> silent 0-bill (effectiveRate now treats <=0 as unset); HIGH overpayment silently absorbed (recordPayment now surfaces the unapplied remainder); HIGH withoutGlobalScopes() stripped SoftDeletes (-> withoutGlobalScope(landlord) keeps deleted_at filtering); MEDIUM two divergent outstanding calcs unified into WaterClientCharge::outstandingForConnection/outstandingByConnection; MEDIUM misconfigured lines now surfaced on the Clients tab; LOW dropped the unwired void policy method. Migration index-name-too-long (>64 chars) fixed with an explicit name.',
    constraints_preserved:
        'Lease-free billing (dedicated table; invoices.lease_id NOT NULL untouched). Effective rate = client_rate ?? landlord water_client_rate, non-positive = unset. Cross-account isolation: readings bounded by connected_at + landlord_id + meter; charge queries keep SoftDeletes (withoutGlobalScope(landlord), not withoutGlobalScopes). Idempotent (unique [connection,period]). Money decimal:2, every op rounded; applyPayment transactional + lockForUpdate. Per-landlord poison-row isolation in the cron. Notifications honour the per-type pref. Water-client charges intentionally exclude the building standing/sewerage/VAT levies (the client_rate is the agreed neighbour price).',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
