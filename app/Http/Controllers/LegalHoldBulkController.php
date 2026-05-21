<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LegalHold;
use App\Services\Legal\BulkHoldService;
use App\Support\LegalHoldRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Phase-65 BULK-HOLD-2: POST/DELETE /legal-holds/bulk for litigation
 * scenarios that touch dozens of subjects at once. Single transaction
 * + ownership pre-check + cache bust once per subject_type.
 */
class LegalHoldBulkController extends Controller
{
    public function __construct(private readonly BulkHoldService $service) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject_type' => ['required', Rule::in(LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES)],
            'subject_ids' => ['required', 'array', 'min:1', 'max:500'],
            'subject_ids.*' => ['integer', 'min:1'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $user = $request->user();

        foreach ($validated['subject_ids'] as $id) {
            if (! $user->can('create', [LegalHold::class, $validated['subject_type'], (int) $id])) {
                throw new AuthorizationException;
            }
        }

        try {
            $this->service->holdAll(
                $validated['subject_type'],
                array_map('intval', $validated['subject_ids']),
                $user,
                $validated['reason'],
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['subject_ids' => __($e->getMessage())]);
        }

        Cache::forget('legal_holds:active:'.$user->id);

        return redirect()->route('legal-holds.list')
            ->with('success', __('legal_holds.create_modal_title'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject_type' => ['required', Rule::in(LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES)],
            'subject_ids' => ['required', 'array', 'min:1', 'max:500'],
            'subject_ids.*' => ['integer', 'min:1'],
        ]);

        $user = $request->user();

        foreach ($validated['subject_ids'] as $id) {
            if (! $user->can('create', [LegalHold::class, $validated['subject_type'], (int) $id])) {
                throw new AuthorizationException;
            }
        }

        try {
            $released = $this->service->releaseAll(
                $validated['subject_type'],
                array_map('intval', $validated['subject_ids']),
                $user,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['subject_ids' => __($e->getMessage())]);
        }

        Cache::forget('legal_holds:active:'.$user->id);

        return redirect()->route('legal-holds.list', ['status' => 'released'])
            ->with('success', "Released {$released} hold(s).");
    }
}
