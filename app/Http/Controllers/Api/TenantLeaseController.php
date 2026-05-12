<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\LimitsPerPage;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeaseResource;
use App\Models\Lease;
use Illuminate\Http\Request;

class TenantLeaseController extends Controller
{
    use LimitsPerPage;

    public function current(Request $request)
    {
        $user = $request->user();

        $lease = Lease::where('tenant_id', $user->id)
            ->where('is_active', true)
            ->with(['unit.building.property', 'rentHistory'])
            ->first();

        if (! $lease) {
            return response()->json([
                'message' => 'No active lease found.',
                'lease' => null,
            ]);
        }

        return new LeaseResource($lease);
    }

    public function history(Request $request)
    {
        $user = $request->user();

        // Phase-15 PERF-3: per_page cap.
        $leases = Lease::where('tenant_id', $user->id)
            ->with(['unit.building.property'])
            ->orderBy('start_date', 'desc')
            ->paginate($this->resolvePerPage($request, default: 10));

        return LeaseResource::collection($leases);
    }
}
