<?php

namespace App\Http\Controllers;

use App\Models\LandlordProfile;
use App\Models\NotificationPreference;
use App\Models\PaymentConfiguration;
use App\Models\Setting;
use App\Services\OcrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingsController extends Controller
{
    /**
     * Display settings page
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Only landlords can access settings
        if (! $user->isLandlord()) {
            abort(403, 'Only landlords can access settings.');
        }

        $landlordId = $user->id;

        // Get landlord profile
        $landlordProfile = LandlordProfile::where('user_id', $landlordId)->first();

        // Get payment configuration
        $paymentConfig = PaymentConfiguration::getOrCreateForLandlord($landlordId);

        // Get current OCR settings (mask sensitive data)
        $ocrSettings = [
            'provider' => Setting::get('ocr_provider', 'none', $landlordId),
            'enabled' => Setting::get('ocr_enabled', 'false', $landlordId) === 'true',
            'auto_verify' => Setting::get('ocr_auto_verify', 'false', $landlordId) === 'true',
        ];

        // Check if API key is set (don't expose the actual key)
        $ocrSettings['has_api_key'] = false;
        if ($ocrSettings['provider'] === 'ocr_space') {
            $ocrSettings['has_api_key'] = ! empty(Setting::get('ocr_space_api_key', null, $landlordId));
        } elseif ($ocrSettings['provider'] === 'google_vision') {
            $ocrSettings['has_api_key'] = ! empty(Setting::get('google_vision_api_key', null, $landlordId));
        } elseif ($ocrSettings['provider'] === 'azure_vision') {
            $ocrSettings['has_api_key'] = ! empty(Setting::get('azure_vision_api_key', null, $landlordId));
        }

        // Get branding settings
        $brandingSettings = [
            'invoice_number_format' => Setting::get('invoice_number_format', 'INV-{YYYY}{MM}-{NNNN}', $landlordId),
            'invoice_footer_text' => Setting::get('invoice_footer_text', '', $landlordId),
            'receipt_footer_text' => Setting::get('receipt_footer_text', '', $landlordId),
            'business_logo_path' => Setting::get('business_logo_path', '', $landlordId),
        ];

        // Generate logo URL if exists
        if ($brandingSettings['business_logo_path']) {
            $brandingSettings['business_logo_url'] = Storage::disk('public')->url($brandingSettings['business_logo_path']);
        } else {
            $brandingSettings['business_logo_url'] = null;
        }

        // Get notification defaults (landlord's own preferences as defaults for new tenants)
        $notificationDefaults = NotificationPreference::where('user_id', $landlordId)
            ->where('landlord_id', $landlordId)
            ->first();

        // Get 2FA status
        $twoFactorEnabled = ! empty($user->two_factor_secret) && ! empty($user->two_factor_confirmed_at);

        // Determine active tab from query string
        $activeTab = $request->query('tab', 'business');

        return Inertia::render('Settings/Index', [
            'activeTab' => $activeTab,
            'landlordProfile' => $landlordProfile,
            'paymentConfig' => $paymentConfig,
            'paymentMethods' => PaymentConfiguration::PAYMENT_METHODS,
            'ocrSettings' => $ocrSettings,
            'ocrProviders' => OcrService::getAvailableProviders(),
            'brandingSettings' => $brandingSettings,
            'notificationDefaults' => $notificationDefaults,
            'twoFactorEnabled' => $twoFactorEnabled,
            'invoiceNumberFormats' => $this->getInvoiceNumberFormats(),
        ]);
    }

    /**
     * Get available invoice number formats
     */
    private function getInvoiceNumberFormats(): array
    {
        return [
            'INV-{YYYY}{MM}-{NNNN}' => 'INV-202501-0001',
            'INV-{NNNN}' => 'INV-0001',
            'INV/{YYYY}/{NNNN}' => 'INV/2025/0001',
            '{YYYY}{MM}{NNNN}' => '2025010001',
            'INVOICE-{NNNN}' => 'INVOICE-0001',
        ];
    }

    /**
     * Update business profile
     */
    public function updateBusinessProfile(Request $request)
    {
        $user = auth()->user();

        if (! $user->isLandlord()) {
            abort(403);
        }

        $validated = $request->validate([
            'company_name' => 'nullable|string|max:255',
            'business_registration_number' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'website' => 'nullable|url|max:255',
        ]);

        $profile = LandlordProfile::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return redirect()->back()->with('success', 'Business profile updated successfully.');
    }

    /**
     * Update payment methods configuration
     */
    public function updatePaymentMethods(Request $request)
    {
        $user = auth()->user();

        if (! $user->isLandlord()) {
            abort(403);
        }

        $validated = $request->validate([
            'accepted_payment_methods' => 'required|array|min:1',
            'accepted_payment_methods.*' => 'string|in:cash,bank_transfer,mobile_money,paystack',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_branch' => 'nullable|string|max:255',
            'mpesa_paybill' => 'nullable|string|max:20',
            'mpesa_account_name' => 'nullable|string|max:255',
            'paystack_enabled' => 'boolean',
        ]);

        $config = PaymentConfiguration::getOrCreateForLandlord($user->id);
        $config->update($validated);

        return redirect()->back()->with('success', 'Payment methods updated successfully.');
    }

    /**
     * Update notification defaults
     */
    public function updateNotificationDefaults(Request $request)
    {
        $user = auth()->user();

        if (! $user->isLandlord()) {
            abort(403);
        }

        $validated = $request->validate([
            'rent_reminder_enabled' => 'boolean',
            'arrears_notice_enabled' => 'boolean',
            'invoice_enabled' => 'boolean',
            'receipt_enabled' => 'boolean',
            'rent_hike_enabled' => 'boolean',
            'lease_expiry_enabled' => 'boolean',
            'maintenance_notice_enabled' => 'boolean',
            'general_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'rent_reminder_days_before' => 'nullable|integer|min:1|max:30',
        ]);

        NotificationPreference::updateOrCreate(
            ['user_id' => $user->id, 'landlord_id' => $user->id],
            $validated
        );

        return redirect()->back()->with('success', 'Notification defaults updated successfully.');
    }

    /**
     * Update OCR settings
     */
    public function updateOcr(Request $request)
    {
        $validated = $request->validate([
            'provider' => 'required|string|in:none,ocr_space,google_vision,azure_vision,tesseract',
            'enabled' => 'required|boolean',
            'auto_verify' => 'required|boolean',
            'api_key' => 'nullable|string',
            'azure_endpoint' => 'nullable|string|url',
        ]);

        $user = auth()->user();

        if (! $user->isLandlord()) {
            abort(403);
        }

        $landlordId = $user->id;

        // Save OCR provider
        Setting::set('ocr_provider', $validated['provider'], false, 'ocr', 'OCR service provider', $landlordId);
        Setting::set('ocr_enabled', $validated['enabled'] ? 'true' : 'false', false, 'ocr', 'Enable OCR processing', $landlordId);
        Setting::set('ocr_auto_verify', $validated['auto_verify'] ? 'true' : 'false', false, 'ocr', 'Auto-verify matching OCR readings', $landlordId);

        // Save API key if provided (encrypted)
        if (! empty($validated['api_key'])) {
            if ($validated['provider'] === 'ocr_space') {
                Setting::set('ocr_space_api_key', $validated['api_key'], true, 'ocr', 'OCR.space API Key', $landlordId);
            } elseif ($validated['provider'] === 'google_vision') {
                Setting::set('google_vision_api_key', $validated['api_key'], true, 'ocr', 'Google Vision API Key', $landlordId);
            } elseif ($validated['provider'] === 'azure_vision') {
                Setting::set('azure_vision_api_key', $validated['api_key'], true, 'ocr', 'Azure Vision API Key', $landlordId);

                if (! empty($validated['azure_endpoint'])) {
                    Setting::set('azure_vision_endpoint', $validated['azure_endpoint'], false, 'ocr', 'Azure Vision Endpoint URL', $landlordId);
                }
            }
        }

        return redirect()->back()->with('success', 'OCR settings updated successfully.');
    }

    /**
     * Test OCR connection
     */
    public function testOcr()
    {
        $user = auth()->user();

        if (! $user->isLandlord()) {
            abort(403);
        }

        $landlordId = $user->id;

        $ocrService = new OcrService;
        $result = $ocrService->testConnection($landlordId);

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        } else {
            return redirect()->back()->with('error', $result['message']);
        }
    }

    /**
     * Update branding settings
     */
    public function updateBranding(Request $request)
    {
        $user = auth()->user();

        if (! $user->isLandlord()) {
            abort(403);
        }

        $validated = $request->validate([
            'invoice_number_format' => 'required|string|max:50',
            'invoice_footer_text' => 'nullable|string|max:500',
            'receipt_footer_text' => 'nullable|string|max:500',
        ]);

        $landlordId = $user->id;

        Setting::set('invoice_number_format', $validated['invoice_number_format'], false, 'branding', 'Invoice number format', $landlordId);
        Setting::set('invoice_footer_text', $validated['invoice_footer_text'] ?? '', false, 'branding', 'Invoice footer text', $landlordId);
        Setting::set('receipt_footer_text', $validated['receipt_footer_text'] ?? '', false, 'branding', 'Receipt footer text', $landlordId);

        return redirect()->back()->with('success', 'Branding settings updated successfully.');
    }

    /**
     * Upload business logo
     */
    public function uploadLogo(Request $request)
    {
        $user = auth()->user();

        if (! $user->isLandlord()) {
            abort(403);
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $landlordId = $user->id;

        // Delete old logo if exists
        $oldLogoPath = Setting::get('business_logo_path', '', $landlordId);
        if ($oldLogoPath && Storage::disk('public')->exists($oldLogoPath)) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        // Store new logo
        $path = $request->file('logo')->store('logos/'.$landlordId, 'public');

        Setting::set('business_logo_path', $path, false, 'branding', 'Business logo path', $landlordId);

        return redirect()->back()->with('success', 'Logo uploaded successfully.');
    }

    /**
     * Delete business logo
     */
    public function deleteLogo()
    {
        $user = auth()->user();

        if (! $user->isLandlord()) {
            abort(403);
        }

        $landlordId = $user->id;

        $logoPath = Setting::get('business_logo_path', '', $landlordId);
        if ($logoPath && Storage::disk('public')->exists($logoPath)) {
            Storage::disk('public')->delete($logoPath);
        }

        Setting::where('landlord_id', $landlordId)
            ->where('key', 'business_logo_path')
            ->delete();

        return redirect()->back()->with('success', 'Logo deleted successfully.');
    }

    /**
     * Delete API key
     */
    public function deleteApiKey(Request $request)
    {
        $validated = $request->validate([
            'provider' => 'required|string|in:ocr_space,google_vision,azure_vision',
        ]);

        $user = auth()->user();

        if (! $user->isLandlord()) {
            abort(403);
        }

        $landlordId = $user->id;

        $keyName = match ($validated['provider']) {
            'ocr_space' => 'ocr_space_api_key',
            'google_vision' => 'google_vision_api_key',
            'azure_vision' => 'azure_vision_api_key',
        };

        Setting::where('landlord_id', $landlordId)
            ->where('key', $keyName)
            ->delete();

        if ($validated['provider'] === 'azure_vision') {
            Setting::where('landlord_id', $landlordId)
                ->where('key', 'azure_vision_endpoint')
                ->delete();
        }

        return redirect()->back()->with('success', 'API key deleted successfully.');
    }
}
