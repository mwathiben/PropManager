<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LegalHold;
use App\Services\Legal\LegalHoldAuditExportService;
use App\Services\Storage\TenantDiskResolver;
use App\Support\LegalHoldRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-68 HISTORY-1/2: per-subject hold/release timeline (chain of
 * custody) for a single holdable subject. Authorized through
 * LegalHoldPolicy::viewHistory — a cross-tenant or non-owned subject is
 * rejected, never returned. The CSV variant streams via the Phase-59
 * signed-URL pattern.
 */
class LegalHoldHistoryController extends Controller
{
    public function __construct(
        private readonly LegalHoldAuditExportService $exporter,
        private readonly TenantDiskResolver $resolver,
    ) {}

    public function show(Request $request): Response
    {
        [$subjectType, $subjectId] = $this->authorizeSubject($request);

        $holds = LegalHold::query()
            ->forSubject($subjectType, $subjectId)
            ->with(['heldBy:id,name', 'releasedBy:id,name'])
            ->orderByDesc('held_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (LegalHold $hold) => [
                'id' => $hold->id,
                'reason' => $hold->reason,
                'held_at' => $hold->held_at?->toIso8601String(),
                'held_by' => $hold->heldBy?->name,
                'released_at' => $hold->released_at?->toIso8601String(),
                'released_by' => $hold->releasedBy?->name,
                'is_active' => $hold->isActive(),
            ]);

        return Inertia::render('LegalHolds/History', [
            'subject' => [
                'type' => $subjectType,
                'short_type' => class_basename($subjectType),
                'id' => $subjectId,
            ],
            'holds' => $holds,
        ]);
    }

    public function export(Request $request): RedirectResponse
    {
        [$subjectType, $subjectId] = $this->authorizeSubject($request);

        $relativePath = $this->exporter->exportSubjectHistoryToCsv($request->user(), $subjectType, $subjectId);

        $filename = 'legal-hold-history-'.class_basename($subjectType).'-'.$subjectId.'.csv';

        $url = $this->resolver->temporaryUrl(
            $relativePath,
            (int) $request->user()->id,
            5,
            $filename,
            'attachment',
        );

        return redirect()->away($url);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function authorizeSubject(Request $request): array
    {
        $validated = $request->validate([
            'subject_type' => ['required', Rule::in(LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES)],
            'subject_id' => ['required', 'integer', 'min:1'],
        ]);

        $subjectType = $validated['subject_type'];
        $subjectId = (int) $validated['subject_id'];

        if (! $request->user()->can('viewHistory', [LegalHold::class, $subjectType, $subjectId])) {
            throw new AuthorizationException;
        }

        return [$subjectType, $subjectId];
    }
}
