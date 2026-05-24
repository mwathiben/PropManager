import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-99-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-24',
    result: '4/4 findings pass — zero deferrals. The water-client journey reaches tenant parity: a water client pays their own invoice online.',
    summary:
        "A water client (role:water_client, bills are real invoices with water_connection_id) pays online through the supplier's gateway via the existing gateway-agnostic checkout (payments.checkout.initialize). InvoicePolicy::pay + the 4 gateway request authorizers gain water_client branches (and a payable-status gate that also closes a pre-existing voided-invoice charge hole for tenants). PaymentController init resolves the payer via Invoice::recipientUser() instead of $invoice->lease->tenant. The gateway callback (PaymentCallbackProcessor), the PaymentReceived broadcast event, the IntaSend webhook, and the receipt service/generator are all made lease-optional (email recipientUser, broadcast to the landlord channel, log water-client overpayment). A new water-client.finances.pay route + WaterClient/Pay.vue + 'Pay now' buttons on the finances page complete the surface; onlinePayEnabled is gated on Paystack resolving for the invoice currency so a Stripe/non-KES route falls back to 'contact supplier' rather than a dead button.",
    review: 'Multi-reviewer read-only pass (CodeRabbit + pr-review code-reviewer + silent-failure-hunter), all three converging. Found + FIXED: HIGH the Stripe/non-KES checkout dead-end (onlinePayEnabled now requires Paystack to resolve for the currency); HIGH a voided-but-unpaid invoice could be charged via the checkout endpoint (added a payable-status gate in InitializePaystackRequest covering tenant + water-client + both controllers); CRITICAL/MEDIUM water-client overpayment silently lost in the gateway callback + IntaSend webhook (added the Phase-98 elseif Log::warning); plus the pre-existing unit->name → unit_number broadcast fix and a callback-notification integration test. Verified clean: authorization has no IDOR (ownership via waterConnection.user_id consistently); recipientUser() is the single payer source across init/callback/receipt; the tenant flow + broadcast payload are unchanged.',
    tests: 'Phase99WaterClientPaymentsOnlineTest 11 (policy own/other/paid, checkout authorizes own [→400 unconfigured] / forbids other [403], pay page renders + forbidden, finances surface, water-aware broadcast event, gateway-callback notifies the client, recordPayment emails the client) + a gateway regression (PaymentController/PaymentCallbackProcessor/IntaSend/receipt tenant paths). Pint clean; eslint baseline holds (pre-existing drift only); nav-audit + imports clean; vite build exit 0; lang parity en/sw/ar. Dev DB needs no new migration (Phase 99 is code-only).',
    constraints_preserved:
        'A water client can only pay their own connection invoice (ownership + payable-status). No new migration. recipientUser() single source. Tenant/landlord/caretaker flows unchanged. Money: checkout caps amount at the remaining balance; overpayment surfaced not silently lost. Kenya-first happy path (Paystack + KES) is the tested, supported online route; Stripe/non-KES and IntaSend-STK self-pay remain a documented follow-up (server authorizers are forward-wired).',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
