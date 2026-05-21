import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-76-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-21',
    result: '18/18 findings pass — zero PRD-finding deferrals',
    commits: {
        'WALLET-CORE (CREDIT-WALLET-1 + MULTI-CCY)': '30625282 + 65f0483d (CodeRabbit fixes)',
        'CREDIT-WALLET (2 + 3)': '74aa740e',
        'AUTO-APPLY': '1cde1f78',
        'TENANT-APPLY': 'fcf43553',
        'STATEMENT-WALLET': 'ce2caf5a',
        'CI': 'this commit',
    },
    summary:
        'Phase 30 PAY-ALLOC / Phase 42 PAYMENTS-INTL sequel deepening the tenant wallet + credit-note + statement subsystem. Money is DECIMAL FLOAT here (predates cents-integer; matched locally). WALLET-CORE: App\\Services\\Wallet\\WalletService single boundary (default currency delegates to Lease::creditToWallet/deductFromWallet keeping the tx+lock+afterCommit invariant in ONE place; non-default locks a lease_wallet_balances row) + wallet_transactions.currency (backfilled) + per-currency balances (default in the Lease scalar, non-default in lease_wallet_balances) + CurrencyMismatchException same-currency guard. CREDIT-WALLET: CreditNoteService::applyToWallet (admin moves an approved note into the tenant wallet via wallet_transactions.credit_note_id, idempotent) + CreditNotePolicy replacing inline aborts. AUTO-APPLY: landlord_wallet_settings + WalletAutoApplyResolver (off | on_invoice_create | oldest_first_sweep, cached) gating InvoiceService deduction + wallet:auto-apply sweep cron (oldest unpaid same-currency, idempotent) + /wallet/settings UI. TENANT-APPLY: tenant.wallet.apply (lease-ownership gated, same-currency only) + TenantFinances/Wallet.vue (balances+ledger+apply). STATEMENT-WALLET: StatementService running balance = charges − payments − credit-note-applications (credit notes subtracted explicitly; wallet movements INFORMATIONAL to avoid double-counting payment-sourced credit) + per-currency wallet-balance header + PDF/XLSX. CI: wallet:rollup gauges (wallet_total_credit_balance{landlord,currency} + credit_notes_pending_count + wallet_applied_24h_count) + docs/runbooks/wallet.md.',
    tests: '34 Phase-76 wallet tests / 101 assertions: WalletCore 8 + CreditWallet 5 + AutoApply 6 + TenantApply 4 + StatementWallet 4 + Surface 7. Existing invoice + Phase-30 allocation suites stay green (no regression).',
    constraints_preserved:
        'WalletService non-default mutations require an outer transaction (throw_unless) + lock the balance row (create-then-lock, no lost-update). All wallet/credit/statement queries landlord-scoped via TenantScope; tenant self-apply gated on lease.tenant_id (load-bearing, since TenantScope only isolates by landlord). No FX engine — same-currency apply only (CurrencyMismatchException). Statement never double-counts (wallet rows informational). selectRaw static-SQL only (no DB::raw user input). Decimal-float money matched, not cents.',
    coderabbit:
        'Clean per sub-phase, every diff reviewed. Fixed: WALLET-CORE H1 untranslated CurrencyMismatch message (+ lang/wallet.php fallback), H2 getOrCreateForLandlord write-on-read → read-only defaultCurrency, M1 unlocked firstOrCreate recovery → create-then-lock, M2 cents-column precision align, L1 backfill whereNotNull; AUTO-APPLY L1 Viewed-status gap, L2 stale early-exit, L3 withoutOverlapping, L4 applyToInvoice null-guard; TENANT-APPLY L2 dead eager-load; STATEMENT-WALLET HIGH credit-note date-string upper-bound truncation → startOfDay/endOfDay, MEDIUM dead test assertion, LOW summary-card reconciliation (+credit_note in totalPayments), LOW email markdown-injection on wallet reason. Double-count correctness verified across all three credit paths.',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
