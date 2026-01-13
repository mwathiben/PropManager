<?php

namespace App\Http\Controllers;

use App\Models\EmergencyContact;
use App\Models\TenantActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class TenantEmergencyContactController extends Controller
{
    public function store(Request $request, User $tenant)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($tenant->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'relationship' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_primary' => 'boolean',
        ]);

        if ($validated['is_primary'] ?? false) {
            EmergencyContact::where('tenant_id', $tenant->id)->update(['is_primary' => false]);
        }

        EmergencyContact::create([
            'landlord_id' => $landlordId,
            'tenant_id' => $tenant->id,
            ...$validated,
        ]);

        TenantActivity::create([
            'landlord_id' => $landlordId,
            'tenant_id' => $tenant->id,
            'performed_by' => $user->id,
            'type' => 'emergency_contact_added',
            'description' => "Emergency contact '{$validated['name']}' was added.",
        ]);

        return Redirect::back()->with('success', 'Emergency contact added.');
    }

    public function update(Request $request, EmergencyContact $contact)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($contact->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'relationship' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_primary' => 'boolean',
        ]);

        if ($validated['is_primary'] ?? false) {
            EmergencyContact::where('tenant_id', $contact->tenant_id)
                ->where('id', '!=', $contact->id)
                ->update(['is_primary' => false]);
        }

        $contact->update($validated);

        return Redirect::back()->with('success', 'Emergency contact updated.');
    }

    public function destroy(EmergencyContact $contact)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($contact->landlord_id !== $landlordId) {
            abort(403);
        }

        $contact->delete();

        return Redirect::back()->with('success', 'Emergency contact deleted.');
    }
}
