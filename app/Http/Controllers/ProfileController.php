<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        // Build user data with role-specific information
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'mobile_number' => $user->mobile_number,
            'profile_photo_url' => $user->profile_photo_url,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
        ];

        // Add tenant-specific KYC data
        if ($user->isTenant()) {
            $userData['national_id'] = $user->national_id;
            $userData['emergency_contact_name'] = $user->emergency_contact_name;
            $userData['emergency_contact_phone'] = $user->emergency_contact_phone;
            $userData['kyc_completed_at'] = $user->kyc_completed_at;
        }

        // Add landlord-specific business profile data
        $landlordProfile = null;
        if ($user->isLandlord()) {
            $profile = $user->landlordProfile;
            if ($profile) {
                $landlordProfile = [
                    'company_name' => $profile->company_name,
                    'business_registration_number' => $profile->business_registration_number,
                    'tax_id' => $profile->tax_id,
                    'address' => $profile->address,
                    'city' => $profile->city,
                    'country' => $profile->country,
                    'website' => $profile->website,
                ];
            }
        }

        return Inertia::render('Profile/Edit', [
            'user' => $userData,
            'landlordProfile' => $landlordProfile,
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Handle photo upload for all users
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

        // Remove the file from validated data
        unset($validated['profile_photo']);

        // Handle landlord business profile separately
        if ($user->isLandlord() && $request->has('business_profile')) {
            $businessData = $request->input('business_profile', []);
            $profile = $user->landlordProfile ?? $user->landlordProfile()->create([]);
            $profile->update([
                'company_name' => $businessData['company_name'] ?? null,
                'business_registration_number' => $businessData['business_registration_number'] ?? null,
                'tax_id' => $businessData['tax_id'] ?? null,
                'address' => $businessData['address'] ?? null,
                'city' => $businessData['city'] ?? null,
                'country' => $businessData['country'] ?? null,
                'website' => $businessData['website'] ?? null,
            ]);
        }

        // Remove business_profile from validated data (handled above)
        unset($validated['business_profile']);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('success', 'Profile updated successfully.');
    }

    /**
     * Update tenant verification (KYC) information.
     */
    public function updateVerification(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isTenant()) {
            abort(403, 'Only tenants can update verification information.');
        }

        $validated = $request->validate([
            'mobile_number' => ['required', 'string', 'max:20'],
            'national_id' => ['required', 'string', 'max:50'],
            'emergency_contact_name' => ['required', 'string', 'max:255'],
            'emergency_contact_phone' => ['required', 'string', 'max:20'],
        ]);

        $user->update($validated);

        // Mark KYC as completed if all fields are filled
        if ($user->hasCompletedKyc() && ! $user->kyc_completed_at) {
            $user->update(['kyc_completed_at' => now()]);
        }

        return Redirect::route('profile.edit')->with('success', 'Verification information updated.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Delete profile photo if exists
        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
