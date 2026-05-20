<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NpsPromptState;
use App\Models\NpsResponse;
use App\Services\Growth\NpsEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Phase-66 NPS-SURVEY-1/2: respond / record-impression / dismiss /
 * opt-out. All four mutate server-authoritative cadence state so the
 * client cannot spam or silence the survey.
 */
class NpsResponseController extends Controller
{
    public function __construct(private NpsEligibilityService $eligibility) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', NpsResponse::class);

        $validated = $request->validate([
            'score' => ['required', 'integer', 'between:0,10'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'context' => ['nullable', 'string', Rule::in((array) config('nps.contexts', []))],
        ]);

        $user = $request->user();

        if ($this->eligibility->hasRespondedRecently($user)) {
            throw ValidationException::withMessages([
                'score' => __('nps.already_responded'),
            ]);
        }

        $score = (int) $validated['score'];

        NpsResponse::create([
            'user_id' => $user->id,
            'score' => $score,
            'category' => NpsResponse::categorise($score),
            'comment' => $validated['comment'] ?? null,
            'context' => $validated['context'] ?? null,
            // The actual impression time, or null when the modal was
            // submitted without a recorded impression — never fabricate
            // now() here, which would corrupt time-to-respond analysis.
            'prompted_at' => NpsPromptState::where('user_id', $user->id)->value('last_prompted_at'),
            'responded_at' => now(),
        ]);

        $this->eligibility->markResponded($user);

        return back(303)->with('success', __('nps.thanks'));
    }

    public function impression(Request $request): RedirectResponse
    {
        $this->authorize('create', NpsResponse::class);

        $this->eligibility->markPrompted($request->user());

        return back(303);
    }

    public function dismiss(Request $request): RedirectResponse
    {
        $this->authorize('create', NpsResponse::class);

        $this->eligibility->markDismissed($request->user());

        return back(303);
    }

    public function optOut(Request $request): RedirectResponse
    {
        $this->authorize('create', NpsResponse::class);

        $this->eligibility->optOut($request->user());

        return back(303);
    }
}
