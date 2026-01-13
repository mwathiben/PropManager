<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use Illuminate\Http\Request;

class BuildingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $perPage = min($request->integer('per_page', 20), 100);

        $buildings = Building::where('landlord_id', $landlordId)
            ->with('property:id,name')
            ->withCount('units')
            ->paginate($perPage);

        return response()->json($buildings);
    }

    public function show(Request $request, Building $building)
    {
        $user = $request->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($building->landlord_id !== $landlordId) {
            abort(403);
        }

        $building->load(['property', 'units']);

        return response()->json(['data' => $building]);
    }

    public function units(Request $request, Building $building)
    {
        $user = $request->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($building->landlord_id !== $landlordId) {
            abort(403);
        }

        $perPage = min($request->integer('per_page', 50), 100);

        $units = $building->units()
            ->with('activeLease.tenant:id,name,email,mobile_number')
            ->paginate($perPage);

        return response()->json($units);
    }
}
