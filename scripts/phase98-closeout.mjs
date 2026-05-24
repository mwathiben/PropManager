import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-98-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-24',
    result: '6/6 findings pass — zero deferrals. USER-DIRECTED unification: water-client billing now runs through the ONE invoicing system.',
    summary:
        "Per the user's directive (one invoicing system, no parallel water-billing track), water-client bills are REAL invoices. invoices.lease_id is nullable + invoices.water_connection_id (exactly one set, enforced by a DB CHECK and an Invoice::booted() creating-guard). InvoiceService::generateInvoiceForWaterConnection mirrors generateInvoiceForLease (water_due only, recipient = the connection's client, currency from the connection's building, status Sent, idempotent per connection+period backed by a unique index). WaterClientBillingService repointed onto it (both guards intact). A water-client invoice is first-class in the finances hub: it lists with the client name + line identifier, opens in the detail modal, downloads as a PDF, and is settled via the standard invoices.recordPayment — with overpayment surfaced (a water-client account has no wallet) rather than NPE-ing on a null lease. Tenants are unchanged: rent + water remain on ONE lease invoice.",
    blast_radius:
        'Every lease-coupled invoice/payment path was made lease-optional: InvoiceController recordPayment + download, the Bank/Mpesa(STK+Till)/BankReconciliation webhook overpayment-to-wallet branches, PaymentReceived + InvoiceSent mailables (+ their blades), InvoicePdfService, InvoiceResource/PaymentResource, FinanceExportService (invoice + payment CSV/PDF), the four Excel export classes (Invoices/StreamingInvoices/Payments/StreamingPayments) + FinancialReportExport, FinanceFilterService + FinancesController (invoice list/detail now show the water client via Invoice::recipientLabel/recipientUser), ManualPaymentHandler. payments.lease_id + receipts.lease_id made nullable so a water-client payment/receipt can persist.',
    retirement:
        'The Phase-97 water_client_charges track is fully retired: table dropped (980200), and WaterClientCharge model/policy/factory, WaterClientBillingService::applyPayment, WaterConnectionController::recordPayment + route + RecordWaterClientPaymentRequest, the ClientsTab record-payment modal, and the orphaned water.record_payment* lang keys (en/sw/ar parity preserved) all removed. No live code references the dropped table or symbols.',
    tests: 'Phase98WaterClientInvoicingTest 16 (billing→invoice metered/flat, idempotency, BOTH guards, dashboard + finances reads, PDF/InvoiceSent null-safety, command emails InvoiceSent, landlord records overpayment without NPE [surfaced not wallet-credited], landlord downloads the PDF, finances list + detail show the client name/identifier, tenant-one-invoice regression, schema/retirement). Regression: full Water suite + InvoiceController + InvoiceWorkflowIntegration + InvoiceService + PdfExportCurrency + Mpesa + Phase81BankRecon green. Pint clean; eslint 0 errors; lang parity preserved. Dev DB migrated.',
    review: 'Multi-reviewer read-only pass (CodeRabbit + pr-review code-reviewer + silent-failure-hunter) on the full diff. Found + FIXED: CRITICAL recordPayment/download + Bank/Mpesa/recon overpayment NPE on a null-lease invoice (guarded with && lease; water-client overpayment surfaced); CRITICAL exports threw on $inv->lease->tenant->name ?? "N/A" (the ?? trap dereferences a null lease first) → null-safe + recipientLabel; CRITICAL payments.lease_id/receipts.lease_id were NOT NULL so a water-client payment could not persist → made nullable; HIGH recipientLabel/recipientUser were dead code + the finances hub showed "N/A" → wired into the list/detail/exports; HIGH no unique backstop replacing wcc_connection_period_unique → added inv_water_conn_period_unique; HIGH/MEDIUM the CHECK is advisory on older MySQL → model-layer XOR guard; MEDIUM stale water.md runbook → rewritten for the unified model.',
    constraints_preserved:
        'Exactly one billing anchor per invoice (lease XOR water connection) at DB + model layers. Effective rate = client_rate ?? landlord water_client_rate, non-positive = unset; the two guards (no_rate / metered_no_meter) never coerce a 0 invoice. Idempotent per connection+period (unique index + lockForUpdate re-check). Money decimal:2, every op rounded. Cross-account isolation preserved (readings bounded by connected_at + landlord_id + the scoped meter). Tenant invoices unchanged — rent + water on ONE invoice.',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
