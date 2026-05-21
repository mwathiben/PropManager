<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Legal\StoreLegalHoldWizardRequest;
use App\Models\LegalHold;
use App\Models\LegalMatter;
use App\Models\User;
use App\Services\Legal\BulkHoldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Phase-72 WIZARD-FLOW: the guided create-hold wizard. create() renders the
 * stepper; store() creates the matter + holds for every chosen subject type in
 * ONE transaction so a partial-hold state is never observable. Subject
 * ownership is re-validated by BulkHoldService at write time.
 */
class LegalHoldWizardController extends Controller
{
    public function __construct(private readonly BulkHoldService $service) {}

    public function create(Request $request): Response
    {
        $this->authorize('create', LegalHold::class);

        $user = $request->user();

        $tenants = User::query()
            ->where('role', 'tenant')
            ->where('landlord_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $t) => ['id' => $t->id, 'name' => $t->name])
            ->values();

        return Inertia::render('LegalHolds/Wizard', [
            'tenants' => $tenants,
            'situations' => collect((array) config('legal_hold.situations', []))
                ->map(fn (array $cfg, string $key) => [
                    'key' => $key,
                    'suggested_types' => $cfg['suggested_types'] ?? [],
                    'review_days' => $cfg['review_days'] ?? null,
                ])
                ->values(),
        ]);
    }

    public function store(StoreLegalHoldWizardRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        try {
            $matter = DB::transaction(function () use ($data, $user) {
                $matter = LegalMatter::create([
                    'title' => $data['title'],
                    'matter_reference' => $data['matter_reference'] ?? null,
                    'situation_type' => $data['situation'] ?? null,
                    'review_by' => $data['review_by'] ?? null,
                    'description' => $data['reason'],
                ]);

                foreach ($data['subjects'] as $subjectClass => $ids) {
                    $ids = array_values(array_unique(array_map('intval', (array) $ids)));
                    if ($ids === []) {
                        continue;
                    }

                    $this->service->holdAll($subjectClass, $ids, $user, $data['reason'], (int) $matter->id);
                }

                return $matter;
            });
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['subjects' => __($e->getMessage())]);
        }

        Cache::forget('legal_holds:active:'.$user->id);

        return redirect()->route('legal-matters.show', $matter)
            ->with('success', __('legal_holds.wizard.created'));
    }
}
