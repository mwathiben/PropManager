<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LegalHold;
use App\Models\LegalMatter;
use App\Models\User;
use App\Services\Legal\HoldSettingsResolver;
use App\Support\LegalHoldRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-65 HOLD-UI-1: landlord-initiated legal-hold CRUD. Index +
 * store + destroy resource so legal counsel can self-serve without
 * an operator. Destroy = release via released_at timestamp, never
 * row delete — audit trail preserved.
 */
class LegalHoldController extends Controller
{
    /**
     * Phase-72 COMMAND-CENTER: the legal-hold home — summary cards, the stale
     * count (per-landlord effective window), the per-matter rollup, and recent
     * activity. The flat list moved to list() (legal-holds.list).
     */
    public function index(Request $request, HoldSettingsResolver $settings): Response
    {
        $this->authorize('viewAny', LegalHold::class);

        $user = $request->user();
        $staleCutoff = now()->subDays($settings->staleAfterDays((int) $user->id));

        $recent = $this->landlordHoldsQuery($user)
            ->with(['heldBy:id,name'])
            ->whereNull('released_at')
            ->orderByDesc('held_at')
            ->limit(5)
            ->get()
            ->map(fn (LegalHold $h) => [
                'id' => $h->id,
                'subject_type' => class_basename($h->holdable_type),
                'subject_id' => $h->holdable_id,
                'reason' => $h->reason,
                'held_at' => $h->held_at?->toIso8601String(),
                'held_by' => $h->heldBy?->name,
            ]);

        $matters = LegalMatter::query()
            ->open()
            ->withCount('activeHolds')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (LegalMatter $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'matter_reference' => $m->matter_reference,
                'review_by' => $m->review_by?->toDateString(),
                'review_due' => $m->isReviewDue(),
                'held_count' => $m->active_holds_count,
            ]);

        return Inertia::render('LegalHolds/Home', [
            'summary' => [
                'active_holds' => LegalHoldRegistry::activeCountForLandlord((int) $user->id),
                'active_matters' => LegalMatter::query()->open()->count(),
                'review_due' => LegalMatter::query()->reviewDue()->count(),
                'stale_holds' => $this->landlordHoldsQuery($user)
                    ->whereNull('released_at')
                    ->where('held_at', '<=', $staleCutoff)
                    ->count(),
            ],
            'matters' => $matters,
            'recent' => $recent,
        ]);
    }

    /**
     * The flat, filterable hold list (formerly index). Route legal-holds.list.
     */
    public function list(Request $request): Response
    {
        $this->authorize('viewAny', LegalHold::class);

        $user = $request->user();
        $status = $request->string('status', 'active')->toString();
        $subjectType = $request->string('subject_type')->toString();

        $query = $this->landlordHoldsQuery($user)
            ->with(['heldBy', 'releasedBy'])
            ->when($status === 'active', fn ($q) => $q->whereNull('released_at'))
            ->when($status === 'released', fn ($q) => $q->whereNotNull('released_at'))
            ->when($subjectType !== '' && in_array($subjectType, LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES, true),
                fn ($q) => $q->where('holdable_type', $subjectType))
            ->orderByDesc($status === 'released' ? 'released_at' : 'held_at')
            ->paginate(25);

        return Inertia::render('LegalHolds/Index', [
            'holds' => $query,
            'filters' => [
                'status' => $status,
                'subject_type' => $subjectType,
            ],
            'subject_types' => LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES,
        ]);
    }

    /**
     * The acting landlord's holds across every holdable type (ownership via the
     * subject's landlord_id, global scopes bypassed).
     */
    private function landlordHoldsQuery(User $user): Builder
    {
        return LegalHold::query()->where(function ($q) use ($user) {
            foreach (LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES as $subjectClass) {
                $heldIds = $subjectClass::query()
                    ->withoutGlobalScopes()
                    ->where('landlord_id', $user->id)
                    ->pluck('id');

                $q->orWhere(function ($qq) use ($subjectClass, $heldIds) {
                    $qq->where('holdable_type', $subjectClass)
                        ->whereIn('holdable_id', $heldIds);
                });
            }
        });
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject_type' => ['required', Rule::in(LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES)],
            'subject_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $user = $request->user();

        if (! $user->can('create', [LegalHold::class, $validated['subject_type'], (int) $validated['subject_id']])) {
            throw new AuthorizationException;
        }

        $subject = $validated['subject_type']::query()
            ->withoutGlobalScopes()
            ->findOrFail($validated['subject_id']);

        LegalHoldRegistry::hold($subject, $user, $validated['reason']);

        Cache::forget('legal_holds:active:'.$user->id);

        return redirect()->route('legal-holds.list')
            ->with('success', __('legal_holds.create_modal_title'));
    }

    public function destroy(Request $request, LegalHold $legalHold): RedirectResponse
    {
        if (! $request->user()->can('release', $legalHold)) {
            throw new AuthorizationException;
        }

        $subjectClass = $legalHold->holdable_type;
        $subject = $subjectClass::query()
            ->withoutGlobalScopes()
            ->findOrFail($legalHold->holdable_id);

        LegalHoldRegistry::release($subject, $request->user());

        Cache::forget('legal_holds:active:'.$request->user()->id);

        return redirect()->route('legal-holds.list', ['status' => 'released'])
            ->with('success', __('legal_holds.release_confirm'));
    }
}
