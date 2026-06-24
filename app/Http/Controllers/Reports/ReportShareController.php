<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\ReportShare;
use App\Models\SavedReport;
use App\Services\MetricsService;
use App\Services\Reports\ReportBuilderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-73 REPORT-SHARE: landlord mints a time-boxed signed link to one of
 * THEIR saved reports; the public view route is signed-gated (no auth) and
 * runs the report with the share row's OWN landlord_id — never a request
 * param. Swapping the share id invalidates the signature; the row adds
 * revocation + an access trail on top of the signature.
 */
class ReportShareController extends Controller
{
    public const EXPIRY_CHOICES = [1, 7, 30];

    public function index(Request $request): Response
    {
        $landlordId = $this->landlordIdFor($request);

        $shares = ReportShare::query()
            ->where('landlord_id', $landlordId)
            ->with('savedReport:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ReportShare $s) => [
                'id' => $s->id,
                'report_name' => $s->savedReport?->name,
                'expires_at' => $s->expires_at->toIso8601String(),
                'revoked' => $s->revoked_at !== null,
                'active' => $s->isActive(),
                'view_count' => $s->view_count,
                'url' => $s->isActive() ? $this->signedUrl($s) : null,
            ]);

        return Inertia::render('Reports/Shares', [
            'shares' => $shares,
            'savedReports' => SavedReport::query()
                ->where('landlord_id', $landlordId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (SavedReport $r) => ['id' => $r->id, 'name' => $r->name]),
            'expiryChoices' => self::EXPIRY_CHOICES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $landlordId = $this->landlordIdFor($request);

        $data = $request->validate([
            'saved_report_id' => [
                'required',
                Rule::exists('saved_reports', 'id')->where(fn ($q) => $q->where('landlord_id', $landlordId)),
            ],
            'expiry_days' => ['required', 'integer', Rule::in(self::EXPIRY_CHOICES)],
        ]);

        ReportShare::create([
            'landlord_id' => $landlordId,
            'saved_report_id' => (int) $data['saved_report_id'],
            'expires_at' => now()->addDays((int) $data['expiry_days']),
        ]);

        return redirect()->route('reports.shares.index')->with('success', __('reports.share.created'));
    }

    public function revoke(ReportShare $share): RedirectResponse
    {
        // Idempotent: keep the original revocation timestamp immutable.
        if ($share->revoked_at === null) {
            $share->update(['revoked_at' => now()]);
        }

        return redirect()->route('reports.shares.index')->with('success', __('reports.share.revoked'));
    }

    /**
     * Public, signed-gated, no-auth read-only view.
     */
    public function view(Request $request, ReportShare $share, ReportBuilderService $builder): View
    {
        // The signature already guarantees integrity + expiry; the row guards
        // revocation + a re-checked expiry (clock skew / pre-expiry revoke).
        abort_unless($share->isActive(), 403);

        $report = SavedReport::query()
            ->withoutGlobalScope('landlord')
            ->where('id', $share->saved_report_id)
            ->where('landlord_id', $share->landlord_id)
            ->firstOrFail(['id', 'name', 'config']);

        // The access happened regardless of render outcome — record it first,
        // atomically.
        $share->increment('view_count');
        $share->forceFill(['last_viewed_at' => now()])->save();
        app(MetricsService::class)->increment('report_share_views_count', 1);

        // A saved report whose config drifted out of the allow-list must not
        // leak a raw 500 to an unauthenticated public viewer — degrade to an
        // "unavailable" state.
        $rows = [];
        $failed = false;
        try {
            $rows = $builder->run($report->config, (int) $share->landlord_id);
        } catch (\Throwable) {
            $failed = true;
        }

        return view('reports.share', [
            'reportName' => $report->name,
            'rows' => $rows,
            'columns' => $rows === [] ? [] : array_keys($rows[0]),
            'expiresAt' => $share->expires_at,
            'failed' => $failed,
        ]);
    }

    private function signedUrl(ReportShare $share): string
    {
        return URL::temporarySignedRoute('reports.share.view', $share->expires_at, ['share' => $share->id]);
    }

    private function landlordIdFor(Request $request): int
    {
        $user = $request->user();

        return $user->effectiveScopeId();
    }
}
