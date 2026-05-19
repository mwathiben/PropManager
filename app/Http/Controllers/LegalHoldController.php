<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Invoice;
use App\Models\LegalHold;
use App\Models\MessageThread;
use App\Models\Ticket;
use App\Support\LegalHoldRegistry;
use Illuminate\Auth\Access\AuthorizationException;
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
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LegalHold::class);

        $user = $request->user();
        $status = $request->string('status', 'active')->toString();
        $subjectType = $request->string('subject_type')->toString();

        $query = LegalHold::query()
            ->with(['heldBy', 'releasedBy'])
            ->where(function ($q) use ($user) {
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
            })
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

        return redirect()->route('legal-holds.index')
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

        return redirect()->route('legal-holds.index', ['status' => 'released'])
            ->with('success', __('legal_holds.release_confirm'));
    }
}
