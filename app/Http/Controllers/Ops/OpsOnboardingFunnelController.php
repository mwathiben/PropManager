<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Services\Onboarding\InvitationFunnelService;
use App\Services\Onboarding\OnboardingFunnelService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-77 FUNNEL-2 / INVITE-FUNNEL-2: super-admin onboarding-health dashboard —
 * per-role step funnel + the platform invitation funnel. Route-gated to
 * role:super_admin (the data is platform-wide, no landlord scope).
 */
class OpsOnboardingFunnelController extends Controller
{
    public function index(OnboardingFunnelService $funnel, InvitationFunnelService $invites): Response
    {
        return Inertia::render('Ops/Onboarding/Funnel', [
            'funnels' => $funnel->all(),
            'inviteFunnel' => $invites->platform(),
        ]);
    }
}
