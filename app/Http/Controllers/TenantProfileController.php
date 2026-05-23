<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Tenant\UpdateTenantNotificationPrefsRequest;
use App\Http\Requests\Tenant\UpdateTenantPasswordRequest;
use App\Http\Requests\Tenant\UpdateTenantProfileRequest;
use App\Models\NotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-28 TENANT-PROFILE-1: dedicated tenant-facing profile so tenants no
 * longer share the landlord-shaped Profile/Edit.vue (which exposes a
 * danger-zone delete that fires landlord-only paths and a business-tab
 * irrelevant to tenants). Routes live under /tenant/profile guarded by
 * role:tenant + payment.verified + kyc.complete.
 */
class TenantProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $landlordId = $user->landlord_id;

        $preference = $landlordId
            ? NotificationPreference::getOrCreate($user->id, $landlordId)
            : null;

        return Inertia::render('Tenant/Profile', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile_number' => $user->mobile_number,
                'profile_photo_url' => $user->profile_photo_url,
                'email_verified_at' => $user->email_verified_at,
                'locale' => $user->locale,
                'emergency_contact_name' => $user->emergency_contact_name,
                'emergency_contact_phone' => $user->emergency_contact_phone,
                'created_at' => $user->created_at,
            ],
            'notificationPreference' => $preference ? [
                'rent_reminder_enabled' => $preference->rent_reminder_enabled,
                'arrears_notice_enabled' => $preference->arrears_notice_enabled,
                'invoice_enabled' => $preference->invoice_enabled,
                'receipt_enabled' => $preference->receipt_enabled,
                'lease_expiry_enabled' => $preference->lease_expiry_enabled,
                'lease_renewal_enabled' => $preference->lease_renewal_enabled,
                'maintenance_notice_enabled' => $preference->maintenance_notice_enabled,
                'general_enabled' => $preference->general_enabled,
                'email_enabled' => $preference->email_enabled,
                'sms_enabled' => $preference->sms_enabled,
                'whatsapp_enabled' => $preference->whatsapp_enabled,
                'push_enabled' => $preference->push_enabled,
                'in_app_enabled' => $preference->in_app_enabled,
                'whatsapp_number' => $preference->whatsapp_number,
            ] : null,
            'supportedLocales' => array_keys(config('app.available_locales', [])),
            'status' => session('status'),
        ]);
    }

    public function update(UpdateTenantProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if ($request->hasFile('profile_photo')) {
            // Store the new photo FIRST and verify it landed — store() returns false
            // (it does not throw) on a disk failure. Deleting the old photo before a
            // failed store would silently destroy it and still report success.
            $path = $request->file('profile_photo')->store('profile-photos/'.$user->id, 'public');
            if ($path === false) {
                throw new \RuntimeException('Failed to store tenant profile photo.');
            }

            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $validated['profile_photo_path'] = $path;
        }

        unset($validated['profile_photo']);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('tenant.profile.edit')
            ->with('success', __('tenant.profile.updated'));
    }

    public function updatePassword(UpdateTenantPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        return Redirect::route('tenant.profile.edit')
            ->with('success', __('tenant.profile.password_updated'));
    }

    public function updateNotificationPrefs(UpdateTenantNotificationPrefsRequest $request): RedirectResponse
    {
        $user = $request->user();
        $landlordId = $user->landlord_id;

        abort_unless($landlordId, 422, 'No landlord context for notification preferences.');

        $preference = NotificationPreference::getOrCreate($user->id, $landlordId);
        $preference->update($request->validated());

        return Redirect::route('tenant.profile.edit')
            ->with('success', __('tenant.profile.notifications_updated'));
    }
}
