<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $perPage = min($request->integer('per_page', 20), 100);

        $properties = Property::where('landlord_id', $landlordId)
            ->with('buildings:id,property_id,name')
            ->paginate($perPage);

        return PropertyResource::collection($properties);
    }

    public function show(Request $request, Property $property)
    {
        $user = $request->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($property->landlord_id !== $landlordId) {
            abort(403);
        }

        $property->load('buildings.units');

        return new PropertyResource($property);
    }
}
