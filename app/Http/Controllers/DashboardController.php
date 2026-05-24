<?php

namespace App\Http\Controllers;

use App\Models\PaymentConfiguration;
use App\Models\Unit;
use App\Models\WaterConnection;
use App\Services\DashboardService;
use App\Services\Water\WaterAccountService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected WaterAccountService $waterAccountService,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();

        return match ($user->role) {
            'super_admin' => $this->superAdminDashboard(),
            'landlord' => $this->landlordDashboard($request),
            'caretaker' => $this->caretakerDashboard(),
            'tenant' => $this->tenantDashboard(),
            'water_client' => $this->waterClientDashboard(),
            // Phase-102: an owner's home is their portal (its own role-gated prefix).
            'owner' => redirect()->route('owner-portal.dashboard'),
            default => abort(403, 'Unknown user role.'),
        };
    }

    protected function superAdminDashboard()
    {
        $data = $this->dashboardService->getSuperAdminMetrics();

        return Inertia::render('Admin/Dashboard', $data);
    }

    protected function landlordDashboard(Request $request)
    {
        $data = $this->dashboardService->getLandlordDashboardData(auth()->user(), $request);

        if (isset($data['redirect'])) {
            return redirect()->route('onboarding.index');
        }

        return Inertia::render('Dashboard', $data);
    }

    protected function caretakerDashboard()
    {
        $data = $this->dashboardService->getCaretakerDashboardData(auth()->user());

        return Inertia::render('Caretaker/Dashboard', $data);
    }

    protected function tenantDashboard()
    {
        $data = $this->dashboardService->getTenantDashboardData(auth()->user());

        return Inertia::render('Tenant/Dashboard', $data);
    }

    /**
     * Phase-95 WATER-CLIENT-ONBOARDING: the landing for a water client (a
     * non-tenant the landlord supplies). A minimal shell — their water line(s) +
     * onboarding status; Phase 96 enriches it with the shared Components/Water/*
     * (consumption history, charges, leak alert) keyed off the connection.
     */
    protected function waterClientDashboard()
    {
        $user = auth()->user();

        // The landlord's default client rate; a connection's own client_rate wins.
        // Never fabricate a system default for a client (Phase-97 biller refuses a
        // null rate) — show "not set" instead, honestly.
        $config = PaymentConfiguration::where('landlord_id', $user->landlord_id)->first();
        $defaultRate = $config?->water_client_rate !== null ? (float) $config->water_client_rate : null;

        $connections = WaterConnection::query()
            ->where('user_id', $user->id)
            ->with(['meter:id,serial_number'])
            ->orderBy('id')
            ->get()
            ->map(function (WaterConnection $c) use ($defaultRate) {
                $account = $this->waterAccountService->overviewForConnection($c);
                $clientRate = $c->client_rate !== null ? (float) $c->client_rate : null;

                return [
                    'id' => $c->id,
                    'identifier' => $c->identifier,
                    'status' => $c->status,
                    'billing_mode' => $c->billing_mode,
                    'meter' => $c->meter?->serial_number,
                    // Derive from the scoped relation (not raw meter_id) so a soft-
                    // deleted/foreign meter reads as "no meter" — matching the empty
                    // account overviewForConnection() returns for the same case.
                    'has_meter' => $c->meter !== null,
                    'effective_rate' => $clientRate ?? $defaultRate,
                    'history' => $account['history'],
                    'summary' => $account['summary'],
                    'alert' => $account['alert'],
                    'charges' => $account['charges'],
                    'disconnection' => $account['disconnection'],
                ];
            });

        return Inertia::render('WaterClient/Dashboard', [
            'connections' => $connections,
            // hasCompletedOnboarding() short-circuits to true for every non-landlord,
            // which would hide the nudge forever — read the actual wizard progress.
            'onboardingComplete' => $user->onboardingProgress?->is_complete ?? false,
        ]);
    }

    public function unitDetail(Unit $unit)
    {
        $user = auth()->user();

        if ($user->role === 'landlord' && $unit->landlord_id !== $user->id) {
            abort(403);
        }

        if ($user->role === 'caretaker' && $unit->landlord_id !== $user->landlord_id) {
            abort(403);
        }

        $data = $this->dashboardService->getUnitDetailData($unit);

        return response()->json($data);
    }
}
