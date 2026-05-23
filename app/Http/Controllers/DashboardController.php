<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
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

        $connections = \App\Models\WaterConnection::query()
            ->where('user_id', $user->id)
            ->with(['meter:id,serial_number'])
            ->get()
            ->map(fn (\App\Models\WaterConnection $c) => [
                'id' => $c->id,
                'identifier' => $c->identifier,
                'status' => $c->status,
                'billing_mode' => $c->billing_mode,
                'meter' => $c->meter?->serial_number,
            ]);

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
