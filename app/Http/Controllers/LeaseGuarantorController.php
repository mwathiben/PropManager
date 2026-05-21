<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Traits\WithLandlordScope;
use App\Models\Lease;
use App\Models\LeaseGuarantor;
use App\Services\Lease\LeaseGuarantorService;
use Illuminate\Http\Request;

/**
 * Phase-83 GUARANTOR-2: landlord/caretaker management of lease guarantors,
 * surfaced on the lease lifecycle view.
 */
class LeaseGuarantorController extends Controller
{
    use WithLandlordScope;

    public function store(Request $request, Lease $lease, LeaseGuarantorService $service)
    {
        abort_unless((int) $lease->landlord_id === $this->getLandlordId(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'relationship' => ['nullable', 'string', 'max:50'],
            'guaranteed_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $service->add($lease, $validated);

        return back()->with('success', __('lease.guarantor.added'));
    }

    public function release(LeaseGuarantor $guarantor, LeaseGuarantorService $service)
    {
        abort_unless((int) $guarantor->landlord_id === $this->getLandlordId(), 403);

        $service->release($guarantor, __('lease.guarantor.released_manual'));

        return back()->with('success', __('lease.guarantor.released'));
    }
}
