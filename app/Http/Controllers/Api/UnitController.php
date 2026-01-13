<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $query = Unit::where('landlord_id', $landlordId)
            ->with(['building:id,name', 'activeLease.tenant:id,name,email']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('building_id')) {
            $query->where('building_id', $request->building_id);
        }

        $perPage = min($request->integer('per_page', 20), 100);
        $units = $query->paginate($perPage);

        return response()->json($units);
    }

    public function show(Request $request, Unit $unit)
    {
        $user = $request->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($unit->landlord_id !== $landlordId) {
            abort(403);
        }

        $unit->load(['building.property', 'activeLease.tenant', 'waterReadings' => fn ($q) => $q->latest()->limit(5)]);

        return response()->json(['data' => $unit]);
    }

    public function updateStatus(Request $request, Unit $unit)
    {
        $request->validate([
            'status' => 'required|in:vacant,occupied,maintenance,arrears',
        ]);

        $user = $request->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($unit->landlord_id !== $landlordId) {
            abort(403);
        }

        $unit->update(['status' => $request->status]);

        return response()->json(['data' => $unit, 'message' => 'Status updated']);
    }
}
