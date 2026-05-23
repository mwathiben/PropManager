import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-95-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-23',
    result: '6/6 findings pass — zero PRD-finding deferrals',
    summary:
        'Water clients become real: a landlord invites/provisions one, they accept a deep-link, onboard (water-only), log in, and land on a working dashboard. LOGIN (role blast radius, all ADDITIVE): role ENUMs (invitations.role + onboarding_sessions.role) widened to water_client; DashboardController water_client arm -> WaterClient/Dashboard shell; AuthenticatedLayout nav + role badge; HandleInertiaRequests getEffectiveCurrency -> supplier landlord_id; useAuth.ts UserRole; role.water_client label (en/sw/ar json). INVITE: invitations.water_connection_id (+ property_id made nullable) + WaterClientInvitationController store/show/accept (separate from the caretaker controller) -> mints a water_client User (role + landlord_id from the invitation, email_verified_at set), links the connection user_id, logs in -> onboarding; WaterClientInvitation mail + StoreWaterClientInvitationRequest/AcceptInvitationRequest (no inline validate); Clients-tab Invite action. ONBOARD: OnboardingFlow::forRole(water_client) = [Profile, Documents, Payment]; WaterClientOnboardingService (payment via shared TenantPaymentMethodService); OnboardingController processorForUser/validateStep/step arms (additive) + Onboarding/WaterClientSteps.vue. Water clients are landlord-provisioned only (not self-registerable, per Phase-94). CI surface + behavioral tests + runbook.',
    tests: 'Phase-95 tests: WaterClientOnboarding 14 (incl. self-register-blocked, register-GET-redirect, duplicate-invite, lowercase-normalize, removed-connection-refused, already-claimed-no-overwrite) + Surface 6. Auth + Onboarding regression 205 passed. Dashboard green except pre-existing Phase55DashboardFiltersTest::missing_building_id_defaults_to_all (scopeFrom(null) default drift — unrelated to Phase 95, confirmed by clean-stash run). Pint/build/eslint(new files 0 errors)/nav-audit clean (water-invite.show allowlisted, now throttled). Lang parity water 277 + role json.',
    review: 'Multi-reviewer read-only pass (CodeRabbit + pr-review-toolkit code-reviewer + silent-failure-hunter). Found + FIXED: CRITICAL water_client self-registration via the /register invitation-token override (now abort 403 + GET redirect to deep-link; the ENUM widen had removed the accidental 500 safety net); HIGH duplicate-pending-invite guard; HIGH accept() errors now shown on the deep-link page; MEDIUM removed-connection refusal (show+accept) + claim-race 0-row-update throw/rollback; MEDIUM dead onboarding nudge (now reads real progress); MEDIUM migration down() de-lossified (clear water_client rows + restore property_id NOT NULL); LOW api-abilities water_client arm (no tenant:read leak), show-route throttle, lowercase email, mail getExpiresAt(), drop card from water-client pay step, drop empty-string profile writes, ar wording.',
    constraints_preserved:
        'All role-branch edits ADDITIVE (new match/if arms only — never modified tenant/caretaker/landlord branches). Water clients landlord-provisioned only (self-registration HARD-blocked at /register, GET + POST). Invite links the connection (user_id) + sets landlord_id (non-null, closing the Phase-94 TenantScope-null gap). One live token per connection. Accept links only an unclaimed live connection (whereNull(user_id) + 0-row throw). email_verified_at set on accept (deep-link proves email). Caretaker InvitationController untouched. FormRequests (no inline validate). property_id nullable for water-client invites; migration down() restores it NOT NULL. Money decimal:2.',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
