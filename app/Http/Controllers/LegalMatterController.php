<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LegalMatter;
use App\Services\Legal\LegalHoldAuditExportService;
use App\Services\Legal\LegalMatterService;
use App\Services\Storage\TenantDiskResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-72 MATTER-GROUPING: case-centric surface over legal holds. Matters are
 * TenantScope-bound so a landlord only ever sees/binds their own; the policy
 * adds the role gate + super-admin bypass.
 */
class LegalMatterController extends Controller
{
    public function __construct(private readonly LegalMatterService $matters) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LegalMatter::class);

        $status = $request->string('status', 'open')->toString();

        $matters = LegalMatter::query()
            ->withCount('activeHolds')
            ->when($status === 'open', fn ($q) => $q->open())
            ->when($status === 'closed', fn ($q) => $q->closed())
            ->orderByDesc('created_at')
            ->paginate(25)
            ->through(fn (LegalMatter $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'matter_reference' => $m->matter_reference,
                'situation_type' => $m->situation_type,
                'status' => $m->status,
                'review_by' => $m->review_by?->toDateString(),
                'review_due' => $m->isReviewDue(),
                'held_count' => $m->active_holds_count,
                'created_at' => $m->created_at?->toIso8601String(),
            ]);

        return Inertia::render('LegalHolds/Matters/Index', [
            'matters' => $matters,
            'filters' => ['status' => $status],
        ]);
    }

    public function show(LegalMatter $matter): Response
    {
        $this->authorize('view', $matter);

        $matter->load(['holds' => fn ($q) => $q->with(['heldBy:id,name', 'releasedBy:id,name'])->orderByDesc('held_at')]);

        $holds = $matter->holds->map(fn ($h) => [
            'id' => $h->id,
            'subject_type' => $h->holdable_type,
            'subject_id' => $h->holdable_id,
            'reason' => $h->reason,
            'is_active' => $h->isActive(),
            'held_at' => $h->held_at?->toIso8601String(),
            'held_by' => $h->heldBy?->name,
            'released_at' => $h->released_at?->toIso8601String(),
            'released_by' => $h->releasedBy?->name,
        ])->values();

        return Inertia::render('LegalHolds/Matters/Show', [
            'matter' => [
                'id' => $matter->id,
                'title' => $matter->title,
                'matter_reference' => $matter->matter_reference,
                'situation_type' => $matter->situation_type,
                'status' => $matter->status,
                'review_by' => $matter->review_by?->toDateString(),
                'review_due' => $matter->isReviewDue(),
                'description' => $matter->description,
                'closed_at' => $matter->closed_at?->toIso8601String(),
                'active_count' => $matter->holds->where('released_at', null)->count(),
            ],
            'holds' => $holds,
        ]);
    }

    public function release(Request $request, LegalMatter $matter): RedirectResponse
    {
        $this->authorize('release', $matter);

        $released = $this->matters->release($matter, $request->user());

        return redirect()->route('legal-matters.show', $matter)
            ->with('success', __('legal_holds.matters.released', ['count' => $released]));
    }

    public function close(Request $request, LegalMatter $matter): RedirectResponse
    {
        $this->authorize('close', $matter);

        if (! $this->matters->canClose($matter)) {
            return redirect()->route('legal-matters.show', $matter)
                ->with('error', __('legal_holds.matters.close_blocked'));
        }

        $matter->close($request->user());

        return redirect()->route('legal-matters.show', $matter)
            ->with('success', __('legal_holds.matters.closed'));
    }

    public function reopen(Request $request, LegalMatter $matter): RedirectResponse
    {
        $this->authorize('close', $matter);

        $matter->reopen();

        return redirect()->route('legal-matters.show', $matter)
            ->with('success', __('legal_holds.matters.reopened'));
    }

    public function auditExport(Request $request, LegalMatter $matter, LegalHoldAuditExportService $exporter): RedirectResponse
    {
        $this->authorize('auditExport', $matter);

        // The CSV is written under the ACTOR's disk tree (exports/{actor}/...),
        // so resolve the signed URL against the actor's disk — not the matter's
        // landlord — or per-landlord disk prefixing / a super-admin actor 404s.
        $path = $exporter->exportMatterToCsv($request->user(), $matter);

        return redirect()->away(
            app(TenantDiskResolver::class)->temporaryUrl($path, (int) $request->user()->id, 5),
        );
    }
}
