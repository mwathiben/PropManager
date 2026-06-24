<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveOut\StoreMoveOutDeductionCategoryRequest;
use App\Http\Requests\MoveOut\UpdateMoveOutDeductionCategoryRequest;
use App\Models\Building;
use App\Models\MoveOutDeductionCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class MoveOutDeductionCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();

        $this->authorize('viewAny', MoveOutDeductionCategory::class);

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $query = MoveOutDeductionCategory::query()
            ->where(function ($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId)
                    ->orWhereNull('landlord_id');
            })
            ->with('building:id,name')
            ->ordered();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $categories = $query->paginate(50)->withQueryString();

        $allCategories = MoveOutDeductionCategory::query()
            ->where(function ($q) use ($landlordId) {
                $q->where('landlord_id', $landlordId)
                    ->orWhereNull('landlord_id');
            })
            ->get(['id', 'is_active', 'always_apply', 'landlord_id']);

        $buildings = Building::where('landlord_id', $landlordId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('MoveOutCategories/Index', [
            'categories' => $categories,
            'buildings' => $buildings,
            'canCreate' => $user->isScopeOwner(),
            'stats' => [
                'total' => $allCategories->count(),
                'active' => $allCategories->where('is_active', true)->count(),
                'always_apply' => $allCategories->where('always_apply', true)->count(),
                'custom' => $allCategories->whereNotNull('landlord_id')->count(),
            ],
            'filters' => [
                'search' => $request->input('search'),
            ],
        ]);
    }

    public function store(StoreMoveOutDeductionCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', MoveOutDeductionCategory::class);

        $validated = $request->validated();

        $nextSortOrder = (MoveOutDeductionCategory::where('landlord_id', $request->user()->id)
            ->max('sort_order') ?? 0) + 1;

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
        $this->authorize('update', $moveOutCategory);

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
