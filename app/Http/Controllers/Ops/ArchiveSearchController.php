<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ops;

use App\Console\Commands\ArchiveRehydrate;
use App\Http\Controllers\Controller;
use App\Models\RehydratedProductEvent;
use App\Services\Archive\ArchiveManifestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ArchiveSearchController extends Controller
{
    public function show(Request $request, ArchiveManifestService $manifest): InertiaResponse
    {
        $landlord = $request->query('landlord');
        $month = $request->query('month');

        $summary = $manifest->summary();
        $monthsForLandlord = $landlord ? $manifest->availableMonthsForLandlord((string) $landlord) : [];
        $landlordsForMonth = $month ? $manifest->availableLandlordsForMonth((string) $month) : [];

        $events = [];
        if ($landlord && $month) {
            $events = RehydratedProductEvent::query()
                ->withoutGlobalScopes()
                ->where('landlord_id', $landlord)
                ->where('source_path', "product-events/{$landlord}/{$month}/events.jsonl.gz")
                ->orderByDesc('original_created_at')
                ->limit(500)
                ->get(['id', 'original_id', 'user_id', 'landlord_id', 'event_name', 'properties', 'original_created_at', 'rehydrated_at'])
                ->toArray();
        }

        return Inertia::render('Ops/ArchiveSearch', [
            'summary' => $summary,
            'selected_landlord' => $landlord,
            'selected_month' => $month,
            'months_for_landlord' => $monthsForLandlord,
            'landlords_for_month' => $landlordsForMonth,
            'events' => $events,
        ]);
    }

    public function rehydrate(Request $request, ArchiveManifestService $manifest): RedirectResponse
    {
        $validated = $request->validate([
            'landlord' => 'required|string|max:64',
            'month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'clear_first' => 'nullable|boolean',
        ]);

        $exit = Artisan::call(ArchiveRehydrate::class, [
            '--landlord' => $validated['landlord'],
            '--month' => $validated['month'],
            '--clear-first' => (bool) ($validated['clear_first'] ?? false),
        ]);

        $manifest->forget($validated['landlord']);

        if ($exit !== 0) {
            return back()->with('error', 'Rehydrate failed — see logs. Output: '.trim(Artisan::output()));
        }

        return redirect()->route('ops.archive.show', [
            'landlord' => $validated['landlord'],
            'month' => $validated['month'],
        ])->with('success', trim(Artisan::output()));
    }
}
