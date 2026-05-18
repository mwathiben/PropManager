<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-54 VENDOR-ONBOARDING-2: signed-URL surface for a Vendor to
 * complete their profile (specialties + service area + phone). Vendor
 * is standalone — no User row, no auth. The signed URL IS the auth;
 * Laravel's `signed` middleware verifies on each request.
 *
 * landlord_id and email are deliberately immutable from this surface:
 * landlord_id is sealed by the URL token, and email mutation is a
 * landlord-side operation (the vendor record reflects the landlord's
 * record of the relationship).
 */
class VendorProfileController extends Controller
{
    public function edit(Vendor $vendor): Response
    {
        return Inertia::render('Vendor/Profile', [
            'vendor' => [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'contact_person' => $vendor->contact_person,
                'phone' => $vendor->phone,
                'address' => $vendor->address,
                'notes' => $vendor->notes,
            ],
        ]);
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $validated = $request->validate([
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $vendor->fill($validated);
        $vendor->save();

        return back()->with('success', __('maintenance.vendor_onboarding.saved'));
    }
}
