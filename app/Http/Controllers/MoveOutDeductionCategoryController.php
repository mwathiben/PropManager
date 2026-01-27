<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveOut\StoreMoveOutDeductionCategoryRequest;
use App\Http\Requests\MoveOut\UpdateMoveOutDeductionCategoryRequest;
use App\Models\Building;
use App\Models\MoveOutDeductionCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class MoveOutDeductionCategoryController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        $this->authorize('viewAny', MoveOutDeductionCategory::class);

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $categories = MoveOutDeductionCategory::query()
            ->where(function ($query) use ($landlordId) {
                $query->where('landlord_id', $landlordId)
                    ->orWhereNull('landlord_id');
            })
            ->with('building')
            ->ordered()
            ->paginate(25)
            ->withQueryString();

        $buildings = Building::where('landlord_id', $landlordId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('MoveOutCategories/Index', [
            'categories' => $categories,
            'buildings' => $buildings,
            'canCreate' => $user->isLandlord(),
        ]);
    }

    public function store(StoreMoveOutDeductionCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $nextSortOrder = MoveOutDeductionCategory::where('landlord_id', $request->user()->id)
            ->max('sort_order') + 1;

        MoveOutDeductionCategory::create([
            'landlord_id' => $request->user()->id,
            'building_id' => $validated['building_id'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'default_amount' => $validated['default_amount'],
            'always_apply' => $validated['always_apply'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $nextSortOrder,
        ]);

        return Redirect::back()->with('success', 'Deduction category created.');
    }

    public function update(
        UpdateMoveOutDeductionCategoryRequest $request,
        MoveOutDeductionCategory $moveOutCategory
    ): RedirectResponse {
        $validated = $request->validated();

        $moveOutCategory->update($validated);

        return Redirect::back()->with('success', 'Deduction category updated.');
    }

    public function destroy(MoveOutDeductionCategory $moveOutCategory): RedirectResponse
    {
        $this->authorize('delete', $moveOutCategory);

        $moveOutCategory->delete();

        return Redirect::back()->with('success', 'Deduction category deleted.');
    }
}
