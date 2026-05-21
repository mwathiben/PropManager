<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\DashboardShare;
use App\Models\LandlordDashboard;
use App\Services\MetricsService;
use App\Services\Reports\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-74 DASH-SHARE: a landlord mints a time-boxed signed link to one of
 * THEIR dashboards; the public view route is signed-gated (no auth) and builds
 * the dashboard with the share row's OWN landlord_id — never a request param.
 * Swapping the share id invalidates the signature; the row adds revocation +
 * an access trail. Mirrors ReportShareController.
 */
class DashboardShareController extends Controller
{
    public const EXPIRY_CHOICES = [1, 7, 30];

    public function index(Request $request): Response
    {
        $landlordId = $this->landlordIdFor($request);

        $shares = DashboardShare::query()
            ->where('landlord_id', $landlordId)
            ->with('landlordDashboard:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DashboardShare $s) => [
                'id' => $s->id,
                'dashboard_name' => $s->landlordDashboard?->name,
                'expires_at' => $s->expires_at->toIso8601String(),
                'revoked' => $s->revoked_at !== null,
                'active' => $s->isActive(),
                'view_count' => $s->view_count,
                'url' => $s->isActive() ? $this->signedUrl($s) : null,
            ]);

        return Inertia::render('Dashboards/Shares', [
            'shares' => $shares,
            'dashboards' => LandlordDashboard::query()
                ->where('landlord_id', $landlordId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (LandlordDashboard $d) => ['id' => $d->id, 'name' => $d->name]),
            'expiryChoices' => self::EXPIRY_CHOICES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $landlordId = $this->landlordIdFor($request);

        $data = $request->validate([
            'landlord_dashboard_id' => [
                'required',
                Rule::exists('landlord_dashboards', 'id')->where(fn ($q) => $q->where('landlord_id', $landlordId)),
            ],
            'expiry_days' => ['required', 'integer', Rule::in(self::EXPIRY_CHOICES)],
        ]);

        DashboardShare::create([
            'landlord_id' => $landlordId,
            'landlord_dashboard_id' => (int) $data['landlord_dashboard_id'],
            'expires_at' => now()->addDays((int) $data['expiry_days']),
        ]);

        return redirect()->route('dashboards.shares.index')->with('success', __('reports.dashboard_share.created'));
    }

    public function revoke(DashboardShare $share): RedirectResponse
    {
        if ($share->revoked_at === null) {
            $share->update(['revoked_at' => now()]);
        }

        return redirect()->route('dashboards.shares.index')->with('success', __('reports.dashboard_share.revoked'));
    }

    /**
     * Public, signed-gated, no-auth read-only view.
     */
    public function view(Request $request, DashboardShare $share, DashboardService $dashboards): View
    {
        abort_unless($share->isActive(), 403);

        $dashboard = LandlordDashboard::query()
            ->withoutGlobalScope('landlord')
            ->where('id', $share->landlord_dashboard_id)
            ->where('landlord_id', $share->landlord_id)
            ->firstOrFail();

        $share->increment('view_count');
        $share->forceFill(['last_viewed_at' => now()])->save();
        app(MetricsService::class)->increment('report_share_views_count', 1, ['surface' => 'dashboard']);

        // A dashboard whose layout drifted out of the allow-list must not leak
        // a raw 500 to an unauthenticated public viewer — degrade to an
        // "unavailable" state.
        $cards = [];
        $failed = false;
        try {
            $cards = $dashboards->buildPayload($dashboard)['cards'];
        } catch (\Throwable) {
            $failed = true;
        }

        return view('dashboards.share', [
            'dashboardName' => $dashboard->name,
            'cards' => $cards,
            'expiresAt' => $share->expires_at,
            'failed' => $failed,
        ]);
    }

    private function signedUrl(DashboardShare $share): string
    {
        return URL::temporarySignedRoute('dashboards.share.view', $share->expires_at, ['share' => $share->id]);
    }

    private function landlordIdFor(Request $request): int
    {
        $user = $request->user();

        return $user->role === 'landlord' ? (int) $user->id : (int) $user->landlord_id;
    }
}
