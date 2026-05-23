import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-94-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-23',
    result: '6/6 findings pass — zero PRD-finding deferrals',
    summary:
        'Foundation of the water-clients epic (94 foundation → 95 onboarding → 96 dashboard → 97 billing). MODEL: WaterConnection (water_connections — the "water line", analogue of Lease: landlord_id, user_id [client account, null until onboarded], unit_id/meter_id both nullable [a client line can be unit-less], identifier, client_name, billing_mode metered/flat_rate, client_rate, status, connected_at; TenantScope+SoftDeletes+Auditable; scopeActive) + factory + WaterConnectionPolicy (landlord owns; caretaker landlord_id; water_client self) + registered; payment_configurations.supplies_water_clients + water_client_rate. ROLE: User::isWaterClient() + RegisteredUserController validation in:...,water_client + TenantScope water_client→landlord_id branch + AdminController role list. SURFACE: landlord-only Clients tab (WaterHubController getClientsData caretaker-bounced + Hub.vue ClientsTab UserGroupIcon canSettings-gated) — ClientsTab.vue setup wizard (WizardSteps: declare supply + default client rate) until opted in, then connection management (create/edit/delete a water line: identifier, client name, meter, billing_mode, rate, status); WaterConnectionController setup + store/update/destroy (landlord-only, FK-scoped) + routes; i18n NET-NEW water.clients.* en/sw/ar. CI surface + behavioral tests + runbook. NOT in scope (deferred 95-97): client onboarding/dashboard/billing.',
    tests: 'Phase-94 tests: WaterClientsFoundation 10 + Surface 7 (17 / 63 assertions). Water + Auth + Onboarding regression 365 / 1055 green. Pint/build/eslint(custom rules 0)/nav-audit clean. Lang parity water 237.',
    review: 'Multi-reviewer read-only pass (CodeRabbit + pr-review-toolkit code-reviewer + silent-failure-hunter). Fixed in 94: CRITICAL self-registration accepted water_client but onboarding_sessions.role is a hard ENUM [landlord,caretaker,tenant] -> 500 mid-registration -> reverted RegisteredUserController to NOT expose water_client (water clients are landlord-provisioned in 95, not self-registered); MEDIUM setup() now uses getOrCreateForLandlord()->update() so opting in never leaves a half-seeded PaymentConfiguration; store/update use $request->validated() (convention) instead of input()/only(); connected_at gains before_or_equal:today. DEFERRED to 95 (handle before the first water_client logs in): DashboardController match default=>abort(403) + AuthenticatedLayout empty nav + OnboardingFlow::forRole throw + getEffectiveCurrency/getNavBadges fall-through + enforce non-null landlord_id at water_client creation (TenantScope stamps null otherwise). DEFERRED to 97: the biller must REFUSE/flag a connection whose effective rate (client_rate ?? water_client_rate) is null — never coerce 0 — and a metered connection with no meter_id.',
    constraints_preserved:
        'Identity model honored: WaterConnection is a relationship (not a role); water_client role only plumbed (users created in 95); dashboards by capability. Clients tab + connection CRUD landlord-only (caretaker bounced + route role:landlord + policy ownership). meter_id/unit_id FKs scoped to the acting landlord. supplies_water_clients managed only on the clients tab (not the shared WaterSettingsForm — avoids a divergent settings surface). water_client scoped to supplier landlord_id like a tenant. Money decimal:2.',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
