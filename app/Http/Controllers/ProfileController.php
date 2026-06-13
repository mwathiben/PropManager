<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\DeleteAccountRequest;
use App\Http\Requests\Profile\UpdateVerificationRequest;
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
    public function edit(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Phase-28 TENANT-PROFILE-1: tenants render the dedicated
        // Tenant/Profile.vue surface instead of the landlord-shaped
        // page that exposes business + danger-zone tabs.
        if ($user->isTenant()) {
            return Redirect::route('tenant.profile.edit');
        }

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
        if ($user->isScopeOwner()) {
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
            // Store the new photo FIRST and verify it landed — store() returns false
            // (it does not throw) on a disk failure. Deleting the old photo before a
            // failed store would silently destroy it and still report success.
            $path = $request->file('profile_photo')->store(
                'profile-photos/'.$user->id,
                'public'
            );
            if ($path === false) {
                throw new \RuntimeException('Failed to store profile photo.');
            }

            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $validated['profile_photo_path'] = $path;
        }

        // Remove the file from validated data
        unset($validated['profile_photo']);

        // Handle landlord business profile separately
        if ($user->isScopeOwner() && $request->has('business_profile')) {
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
    public function updateVerification(UpdateVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        // Phase-46 CANONICAL-FIX-1: stopped writing kyc_completed_at —
        // it's deprecated (mirror_exempt; remove_at 2026-08-17). Callers
        // that need the verified-at timestamp use User::kycVerifiedAt(),
        // which reads MAX(approved_at) from tenant_kyc_submissions.

        return Redirect::route('profile.edit')->with('success', 'Verification information updated.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(DeleteAccountRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        // AUDIT-7: log BEFORE Auth::logout() so the audit row carries the
        // self-deleting user's id. After logout, Auth::id() is null and the
        // automatic deleted-event audit row would lose the actor.
        $user->logCustomAudit('account_force_deleted', [
            'email' => $user->email,
            'self_initiated' => true,
        ]);

        Auth::logout();

        $user->forceDelete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
