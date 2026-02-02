<?php

namespace App\Http\Controllers;

use App\Http\Requests\Kyc\StoreKycRequirementRequest;
use App\Http\Requests\Kyc\UpdateKycRequirementRequest;
use App\Models\Building;
use App\Models\KycRequirement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class KycRequirementController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', KycRequirement::class);

        $landlordId = Auth::id();

        $platformDefaults = KycRequirement::withoutGlobalScope('landlord')
            ->global()
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($req) => array_merge($req->toArray(), ['is_platform_default' => true]));

        $landlordRequirements = KycRequirement::where('landlord_id', $landlordId)
            ->with('building:id,name')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($req) => array_merge($req->toArray(), ['is_platform_default' => false]));

        $allRequirements = $platformDefaults->concat($landlordRequirements);

        $buildings = Building::where('landlord_id', $landlordId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Settings/KycRequirements', [
            'requirements' => [
                'data' => $allRequirements,
            ],
            'buildings' => $buildings,
        ]);
    }

    public function store(StoreKycRequirementRequest $request): RedirectResponse
    {
        $this->authorize('create', KycRequirement::class);

        $data = $request->validated();
        $data['landlord_id'] = Auth::id();

        if (! isset($data['sort_order'])) {
            $maxSortOrder = KycRequirement::where('landlord_id', Auth::id())
                ->max('sort_order') ?? 0;
            $data['sort_order'] = $maxSortOrder + 1;
        }

        KycRequirement::create($data);

        return redirect()->back()->with('success', 'KYC requirement created successfully.');
    }

    public function update(UpdateKycRequirementRequest $request, KycRequirement $kycRequirement): RedirectResponse
    {
        $this->authorize('update', $kycRequirement);

        $kycRequirement->update($request->validated());

        return redirect()->back()->with('success', 'KYC requirement updated successfully.');
    }

    public function destroy(KycRequirement $kycRequirement): RedirectResponse
    {
        $this->authorize('delete', $kycRequirement);

        $kycRequirement->delete();

        return redirect()->back()->with('success', 'KYC requirement deleted successfully.');
    }
}
