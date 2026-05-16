<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\Experiment;
use App\Models\ExperimentExposure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Phase-37 PWA-FRONTEND-ADMIN-2/3: super_admin CRUD for Phase-35
 * experiments. Replaces raw-SQL experiment lifecycle work with a
 * proper admin surface — index list, per-experiment detail, status
 * flip, conclude with winning_variant_key.
 */
class ExperimentController extends Controller
{
    public function index(): InertiaResponse
    {
        $experiments = Experiment::query()
            ->orderByDesc('starts_at')
            ->get()
            ->map(function (Experiment $experiment) {
                $exposuresByVariant = ExperimentExposure::query()
                    ->where('experiment_key', $experiment->experiment_key)
                    ->groupBy('variant_key')
                    ->selectRaw('variant_key, count(*) as c')
                    ->pluck('c', 'variant_key')
                    ->all();

                return [
                    'id' => $experiment->id,
                    'experiment_key' => $experiment->experiment_key,
                    'name' => $experiment->name,
                    'status' => $experiment->status,
                    'variants' => $experiment->variants,
                    'winning_variant_key' => $experiment->winning_variant_key,
                    'starts_at' => $experiment->starts_at?->toIso8601String(),
                    'ends_at' => $experiment->ends_at?->toIso8601String(),
                    'exposures_by_variant' => $exposuresByVariant,
                    'exposures_total' => array_sum($exposuresByVariant),
                ];
            });

        return Inertia::render('Ops/Experiments/Index', [
            'experiments' => $experiments,
            'statuses' => Experiment::STATUSES,
        ]);
    }

    public function show(Experiment $experiment): InertiaResponse
    {
        $exposuresByVariant = ExperimentExposure::query()
            ->where('experiment_key', $experiment->experiment_key)
            ->groupBy('variant_key')
            ->selectRaw('variant_key, count(*) as c')
            ->pluck('c', 'variant_key')
            ->all();

        return Inertia::render('Ops/Experiments/Show', [
            'experiment' => [
                'id' => $experiment->id,
                'experiment_key' => $experiment->experiment_key,
                'name' => $experiment->name,
                'status' => $experiment->status,
                'variants' => $experiment->variants,
                'winning_variant_key' => $experiment->winning_variant_key,
                'starts_at' => $experiment->starts_at?->toIso8601String(),
                'ends_at' => $experiment->ends_at?->toIso8601String(),
            ],
            'exposures_by_variant' => $exposuresByVariant,
            'exposures_total' => array_sum($exposuresByVariant),
            'statuses' => Experiment::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'experiment_key' => 'required|string|max:64|unique:experiments,experiment_key',
            'name' => 'required|string|max:255',
            'variants' => 'required|array|min:2',
            'variants.*.key' => 'required|string|max:64',
            'variants.*.weight' => 'required|integer|min:0|max:100',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        Experiment::create([
            'experiment_key' => $validated['experiment_key'],
            'name' => $validated['name'],
            'status' => Experiment::STATUS_DRAFT,
            'variants' => $validated['variants'],
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
        ]);

        return redirect()->route('ops.experiments.index')
            ->with('success', 'Experiment created.');
    }

    public function update(Request $request, Experiment $experiment): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:'.implode(',', Experiment::STATUSES),
        ]);

        $experiment->update(['status' => $validated['status']]);

        return back()->with('success', 'Experiment status updated.');
    }

    public function conclude(Request $request, Experiment $experiment): RedirectResponse
    {
        $validated = $request->validate([
            'winning_variant_key' => 'nullable|string|max:64',
        ]);

        $experiment->update([
            'status' => Experiment::STATUS_CONCLUDED,
            'winning_variant_key' => $validated['winning_variant_key'] ?? null,
            'ends_at' => now(),
        ]);

        return back()->with('success', 'Experiment concluded.');
    }
}
