<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TenantKycController extends Controller
{
    /**
     * Display the KYC completion form.
     */
    public function show(): Response
    {
        $user = auth()->user();

        return Inertia::render('Tenant/CompleteKyc', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'mobile_number' => $user->mobile_number,
                'national_id' => $user->national_id,
                'emergency_contact_name' => $user->emergency_contact_name,
                'emergency_contact_phone' => $user->emergency_contact_phone,
                'profile_photo_url' => $user->profile_photo_url,
            ],
        ]);
    }

    /**
     * Update the user's KYC information.
     */
    public function update(Request $request)
    {
        $user = auth()->user();

        // Photo is required only if not already uploaded
        $photoRule = $user->profile_photo_path
            ? ['nullable', 'image', 'max:2048']
            : ['required', 'image', 'max:2048'];

        $validated = $request->validate([
            'mobile_number' => ['required', 'string', 'max:20'],
            'national_id' => ['required', 'string', 'max:50'],
            'emergency_contact_name' => ['required', 'string', 'max:255'],
            'emergency_contact_phone' => ['required', 'string', 'max:20'],
            'profile_photo' => $photoRule,
        ], [
            'mobile_number.required' => 'Please enter your phone number.',
            'national_id.required' => 'Please enter your National ID or Passport number.',
            'emergency_contact_name.required' => 'Please enter an emergency contact name.',
            'emergency_contact_phone.required' => 'Please enter an emergency contact phone number.',
            'profile_photo.required' => 'Please upload a profile photo.',
            'profile_photo.image' => 'The profile photo must be an image file.',
            'profile_photo.max' => 'The profile photo must not exceed 2MB.',
        ]);

        // Handle photo upload
        if ($request->hasFile('profile_photo')) {
            // Delete old photo if exists
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $path = $request->file('profile_photo')->store(
                'profile-photos/'.$user->id,
                'public'
            );
            $validated['profile_photo_path'] = $path;
        }

        // Remove the file from validated data (we've handled it above)
        unset($validated['profile_photo']);

        // Mark KYC as completed
        $validated['kyc_completed_at'] = now();

        $user->update($validated);

        return redirect()->route('dashboard')
            ->with('success', 'Profile completed successfully! Welcome to your dashboard.');
    }
}
