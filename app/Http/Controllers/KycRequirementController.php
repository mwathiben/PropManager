<?php

namespace App\Http\Controllers;

use App\Http\Requests\Kyc\StoreKycRequirementRequest;
use App\Http\Requests\Kyc\UpdateKycRequirementRequest;
use App\Models\Building;
use App\Models\KycRequirement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class KycRequirementController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        $this->authorize('viewAny', KycRequirement::class);

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $requirements = KycRequirement::withoutGlobalScope('landlord')
            ->where(function ($query) use ($landlordId) {
                $query->where('landlord_id', $landlordId)
                    ->orWhereNull('landlord_id');
            })
            ->with('building:id,name')
            ->ordered()
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($req) => array_merge(
                $req->toArray(),
                ['is_platform_default' => $req->landlord_id === null]
            ));

        $buildings = Building::where('landlord_id', $landlordId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Settings/KycRequirements', [
            'requirements' => $requirements,
            'buildings' => $buildings,
            'canCreate' => $user->isLandlord(),
        ]);
    }

    public function store(StoreKycRequirementRequest $request): RedirectResponse
    {
        $this->authorize('create', KycRequirement::class);

        $validated = $request->validated();

        $nextSortOrder = (KycRequirement::where('landlord_id', $request->user()->id)
            ->max('sort_order') ?? 0) + 1;

        KycRequirement::create([
            'landlord_id' => $request->user()->id,
            'building_id' => $validated['building_id'] ?? null,
            'requirement_type' => $validated['requirement_type'],
            'label' => $validated['label'],
            'description' => $validated['description'] ?? null,
            'is_required' => $validated['is_required'] ?? true,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $nextSortOrder,
        ]);

        return Redirect::back()->with('success', 'KYC requirement created.');
    }

    public function update(UpdateKycRequirementRequest $request, KycRequirement $kycRequirement): RedirectResponse
    {
        $this->authorize('update', $kycRequirement);

        $kycRequirement->update($request->validated());

        return Redirect::back()->with('success', 'KYC requirement updated.');
    }

    public function destroy(KycRequirement $kycRequirement): RedirectResponse
    {
        $this->authorize('delete', $kycRequirement);

        $kycRequirement->delete();

        return Redirect::back()->with('success', 'KYC requirement deleted.');
    }
}
