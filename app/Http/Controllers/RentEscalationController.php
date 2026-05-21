<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Traits\WithLandlordScope;
use App\Models\Lease;
use App\Models\RentEscalation;
use App\Services\Lease\RentEscalationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Phase-83 RENT-ESCALATION-3: landlord scheduling/cancelling of future rent
 * increases, surfaced on the lease lifecycle view.
 */
class RentEscalationController extends Controller
{
    use WithLandlordScope;

    public function store(Request $request, Lease $lease, RentEscalationService $service)
    {
        abort_unless((int) $lease->landlord_id === $this->getLandlordId(), 403);

        $validated = $request->validate([
            'escalation_type' => ['required', Rule::in([
                RentEscalation::TYPE_PERCENTAGE,
                RentEscalation::TYPE_FIXED_AMOUNT,
            ])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'effective_date' => ['required', 'date', 'after:today'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $service->schedule($lease, $validated);

        return back()->with('success', __('lease.escalation.scheduled'));
    }

    public function destroy(RentEscalation $escalation, RentEscalationService $service)
    {
        abort_unless((int) $escalation->landlord_id === $this->getLandlordId(), 403);

        $service->cancel($escalation);

        return back()->with('success', __('lease.escalation.cancelled'));
    }
}
