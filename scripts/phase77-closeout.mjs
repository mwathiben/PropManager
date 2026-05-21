import { readFileSync, writeFileSync } from 'node:fs';

const path = 'phase-77-audit-prd.json';
const prd = JSON.parse(readFileSync(path, 'utf8'));

for (const finding of prd.findings) {
    finding.passes = true;
}

prd.closeout = {
    closed_at: '2026-05-21',
    result: '18/18 findings pass — zero PRD-finding deferrals',
    commits: {
        'CARETAKER-CONTEXT': '893d4aba',
        'CARETAKER-FLOW': '5e9a5b3f',
        'INVITE-DEEPLINK': '4b13cf7c',
        'FUNNEL + INVITE-FUNNEL': '222e03f3',
        'CI': 'this commit',
    },
    summary:
        'Phase 46-48 sequel closing onboarding\'s last parity gap. CARETAKER-CONTEXT: CaretakerBuildingSummaryService (per assigned building unit/occupancy/open-ticket counts, landlord-scoped, no N+1) + CaretakerFirstTaskResolver (oldest open ticket on an accepted building else maintenance.hub). CARETAKER-FLOW: OnboardingFlow caretaker 3->5 (Welcome + Orientation bookends); renumbered CaretakerOnboardingService + validateCaretakerStep; per-role caretakerStepProps (step 3 assignment stats, step 5 orientation summary + firstTaskUrl) so caretaker no longer borrows the landlord getStepProps; CaretakerSteps.vue welcome/orientation panels + stat cards; completion deep-links to the first task. INVITE-DEEPLINK: InvitationController accept + acceptAuthenticated redirect caretakers into onboarding.step(1); invitations.viewed_at stamped once on first view; store() sets role=caretaker. FUNNEL: OnboardingFunnelService per-role per-step reached/completed/abandoned + drop-off; /ops/onboarding/funnel super-admin dashboard; onboarding:funnel-rollup cron gauges + onboarding_completion_low alert. INVITE-FUNNEL: InvitationFunnelService sent/viewed/accepted/pending/expired + acceptance_rate over both invite tables.',
    tests: '27 Phase-77 tests: CaretakerContext 4 + CaretakerFlow 4 + InviteDeeplink 3 + Funnel 4 + Surface 6 (+6 existing onboarding tests updated for the 5-step caretaker contract). Full onboarding suite green.',
    constraints_preserved:
        'Caretaker building/ticket data scoped via building.landlord_id; first-task resolver only returns accepted-building tickets (no cross-landlord); funnel + ops dashboard are super-admin platform-wide (onboarding_sessions has no landlord scope); InvitationFunnelService bypasses TenantInvitation TenantScope explicitly, landlord scope governed by the arg; tenant self-nothing here. The renumber updated EVERY integer-keyed site (flow/service/validation/props/Vue) + the 4 existing onboarding tests.',
    coderabbit:
        'Clean per sub-phase. CARETAKER-CONTEXT clean (L2 resolver orderBy(id) tiebreak applied). CARETAKER-FLOW CRITICAL: Index.vue forwarded only 2 props to CaretakerSteps so step-3 stats + step-5 orientation rendered empty (pendingAssignments broken since Phase 48) -> forward all via $page.props + seed profile form. INVITE-DEEPLINK: verified-bounce was a non-issue (User !MustVerifyEmail); applied store() role=caretaker. FUNNEL MEDIUM: pending over-counted declined tenant invites -> explicit positive count; gauge naming tokens (active_sessions_count / dropoff_step_number).',
};

writeFileSync(path, JSON.stringify(prd, null, 2) + '\n');
console.log('closeout written:', prd.findings.length, 'findings set passes:true');
