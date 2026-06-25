<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ClauseType;
use App\Exceptions\DataIntegrityException;
use App\Http\Requests\ComposeManagementAgreementRequest;
use App\Models\Clause;
use App\Models\ManagementAgreement;
use App\Models\PropertyOwner;
use App\Services\Agreements\AgreementComposer;
use App\Services\Agreements\AgreementSender;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Slice-2 PR-2.2: the manager-facing management-agreement composer. Compose a
 * DRAFT + preview; owner invite / e-sign / fee-apply land in PR 2.3.
 */
class AgreementController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('viewAny', ManagementAgreement::class);

        return Inertia::render('Agreements/Index', [
            'agreements' => ManagementAgreement::query()
                ->with('propertyOwner:id,name')
                ->latest()
                ->paginate(15)
                ->through(fn (ManagementAgreement $agreement): array => [
                    'id' => $agreement->id,
                    'title' => $agreement->title,
                    'status' => $agreement->status->value,
                    'owner_name' => $agreement->propertyOwner?->name,
                    'created_at' => $agreement->created_at?->toDateString(),
                ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ManagementAgreement::class);

        return Inertia::render('Agreements/Compose', [
            'owners' => PropertyOwner::query()->active()->orderBy('name')->get(['id', 'name', 'email']),
            'clauses' => Clause::query()->active()->where('type', ClauseType::Management)->orderBy('binding')
                ->get(['id', 'key', 'binding', 'title', 'explanation', 'body_template', 'params_schema', 'is_exclusive']),
        ]);
    }

    public function store(ComposeManagementAgreementRequest $request, AgreementComposer $composer): RedirectResponse
    {
        $agreement = $composer->composeDraft($request->user(), $request->validated());

        return redirect()->route('agreements.show', $agreement)
            ->with('success', __('agreements.draft_created'));
    }

    public function send(ManagementAgreement $agreement, AgreementSender $sender): RedirectResponse
    {
        $this->authorize('send', $agreement);

        try {
            $sender->send($agreement);
        } catch (DataIntegrityException $e) {
            $key = $e->getErrorCode() === 'agreement.owner_contact_missing'
                ? 'agreements.sign.errors.owner_contact_missing'
                : 'agreements.sign.errors.not_sendable';

            return back()->withErrors(['agreement' => __($key)]);
        }

        return redirect()->route('agreements.show', $agreement)
            ->with('success', __('agreements.sign.sent'));
    }

    public function show(ManagementAgreement $agreement): Response
    {
        $this->authorize('view', $agreement);

        $agreement->load('propertyOwner:id,name,email', 'agreementClauses.clause');

        return Inertia::render('Agreements/Show', [
            'agreement' => [
                'id' => $agreement->id,
                'title' => $agreement->title,
                'status' => $agreement->status->value,
                'owner' => $agreement->propertyOwner?->only(['id', 'name', 'email']),
                'rendered_body' => $agreement->rendered_body,
                'content_hash' => $agreement->content_hash,
                'clauses' => $agreement->agreementClauses->map(fn ($instance): array => [
                    'title' => $instance->clause?->title,
                    'explanation' => $instance->clause?->explanation,
                    'body' => $instance->render(),
                ]),
            ],
        ]);
    }
}
