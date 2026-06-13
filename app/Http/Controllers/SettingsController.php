<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\DeleteApiKeyRequest;
use App\Http\Requests\Settings\UpdateBrandingRequest;
use App\Http\Requests\Settings\UpdateBusinessProfileRequest;
use App\Http\Requests\Settings\UpdateNotificationDefaultsRequest;
use App\Http\Requests\Settings\UpdateOcrRequest;
use App\Http\Requests\Settings\UpdatePaymentMethodsRequest;
use App\Http\Requests\Settings\UploadLogoRequest;
use App\Models\LandlordProfile;
use App\Models\NotificationPreference;
use App\Models\PaymentConfiguration;
use App\Models\Setting;
use App\Services\OcrService;
use App\Services\SecurityLogger;
use App\Services\Settings\PaymentMethodConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function __construct(protected PaymentMethodConfigService $configService) {}

    /**
     * Display settings page
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if (! $user->isLandlord()) {
            abort(403, 'Only landlords can access settings.');
        }

        $landlordId = $user->id;

        $notificationDefaults = NotificationPreference::where('user_id', $landlordId)
            ->where('landlord_id', $landlordId)
            ->first();

        $twoFactorEnabled = ! empty($user->two_factor_secret) && ! empty($user->two_factor_confirmed_at);

        return Inertia::render('Settings/Index', [
            'activeTab' => $request->query('tab', 'business'),
            'landlordProfile' => LandlordProfile::where('user_id', $landlordId)->first(),
            'paymentConfig' => $this->configService->maskedConfig($user),
            'paymentMethods' => PaymentConfiguration::getAvailablePaymentMethods(),
            'ocrSettings' => $this->resolveOcrSettings($landlordId),
            'ocrProviders' => OcrService::getAvailableProviders(),
            'brandingSettings' => $this->resolveBrandingSettings($landlordId),
            'notificationDefaults' => $notificationDefaults,
            'twoFactorEnabled' => $twoFactorEnabled,
            'invoiceNumberFormats' => $this->getInvoiceNumberFormats(),
        ]);
    }

    private function resolveOcrSettings(int $landlordId): array
    {
        $provider = Setting::get('ocr_provider', 'none', $landlordId);
        $apiKeyMap = [
            'ocr_space' => 'ocr_space_api_key',
            'google_vision' => 'google_vision_api_key',
            'azure_vision' => 'azure_vision_api_key',
        ];
        $hasApiKey = isset($apiKeyMap[$provider])
            && ! empty(Setting::get($apiKeyMap[$provider], null, $landlordId));

        return [
            'provider' => $provider,
            'enabled' => Setting::get('ocr_enabled', 'false', $landlordId) === 'true',
            'auto_verify' => Setting::get('ocr_auto_verify', 'false', $landlordId) === 'true',
            'has_api_key' => $hasApiKey,
        ];
    }

    private function resolveBrandingSettings(int $landlordId): array
    {
        $logoPath = Setting::get('business_logo_path', '', $landlordId);

        return [
            'invoice_number_format' => Setting::get('invoice_number_format', 'INV-{YYYY}{MM}-{NNNN}', $landlordId),
            'invoice_footer_text' => Setting::get('invoice_footer_text', '', $landlordId),
            'receipt_footer_text' => Setting::get('receipt_footer_text', '', $landlordId),
            'business_logo_path' => $logoPath,
            'business_logo_url' => $logoPath ? Storage::disk('public')->url($logoPath) : null,
        ];
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
    public function updateBusinessProfile(UpdateBusinessProfileRequest $request)
    {
        $profile = LandlordProfile::updateOrCreate(
            ['user_id' => auth()->id()],
            $request->validated()
        );

        return redirect()->back()->with('success', 'Business profile updated successfully.');
    }

    /**
     * Update payment methods configuration.
     *
     * Delegates to PaymentMethodConfigService — the canonical credential writer.
     * This keeps the settings.payment.update route working as a compatibility shim.
     */
    public function updatePaymentMethods(UpdatePaymentMethodsRequest $request, SecurityLogger $securityLogger)
    {
        $this->configService->apply(auth()->user(), $request->validated(), $securityLogger);

        return redirect()->back()->with('success', 'Payment methods updated successfully.');
    }

    /**
     * Update notification defaults
     */
    public function updateNotificationDefaults(UpdateNotificationDefaultsRequest $request)
    {
        $userId = auth()->id();

        NotificationPreference::updateOrCreate(
            ['user_id' => $userId, 'landlord_id' => $userId],
            $request->validated()
        );

        return redirect()->back()->with('success', 'Notification defaults updated successfully.');
    }

    /**
     * Update OCR settings
     */
    public function updateOcr(UpdateOcrRequest $request)
    {
        $validated = $request->validated();
        $landlordId = auth()->id();

        Setting::set('ocr_provider', $validated['provider'], false, 'ocr', 'OCR service provider', $landlordId);
        Setting::set('ocr_enabled', $validated['enabled'] ? 'true' : 'false', false, 'ocr', 'Enable OCR processing', $landlordId);
        Setting::set('ocr_auto_verify', $validated['auto_verify'] ? 'true' : 'false', false, 'ocr', 'Auto-verify matching OCR readings', $landlordId);

        if (! empty($validated['api_key'])) {
            $this->saveOcrApiKey($validated['provider'], $validated['api_key'], $validated['azure_endpoint'] ?? null, $landlordId);
        }

        return redirect()->back()->with('success', 'OCR settings updated successfully.');
    }

    private function saveOcrApiKey(string $provider, string $apiKey, ?string $azureEndpoint, int $landlordId): void
    {
        $providerKeyMap = [
            'ocr_space' => ['ocr_space_api_key', 'OCR.space API Key'],
            'google_vision' => ['google_vision_api_key', 'Google Vision API Key'],
            'azure_vision' => ['azure_vision_api_key', 'Azure Vision API Key'],
        ];

        if (! isset($providerKeyMap[$provider])) {
            return;
        }

        [$settingKey, $label] = $providerKeyMap[$provider];
        Setting::set($settingKey, $apiKey, true, 'ocr', $label, $landlordId);

        if ($provider === 'azure_vision' && ! empty($azureEndpoint)) {
            Setting::set('azure_vision_endpoint', $azureEndpoint, false, 'ocr', 'Azure Vision Endpoint URL', $landlordId);
        }
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
    public function updateBranding(UpdateBrandingRequest $request)
    {
        $validated = $request->validated();
        $landlordId = auth()->id();

        Setting::set('invoice_number_format', $validated['invoice_number_format'], false, 'branding', 'Invoice number format', $landlordId);
        Setting::set('invoice_footer_text', $validated['invoice_footer_text'] ?? '', false, 'branding', 'Invoice footer text', $landlordId);
        Setting::set('receipt_footer_text', $validated['receipt_footer_text'] ?? '', false, 'branding', 'Receipt footer text', $landlordId);

        return redirect()->back()->with('success', 'Branding settings updated successfully.');
    }

    /**
     * Upload business logo
     */
    public function uploadLogo(UploadLogoRequest $request)
    {
        $landlordId = auth()->id();

        $oldLogoPath = Setting::get('business_logo_path', '', $landlordId);
        if ($oldLogoPath && Storage::disk('public')->exists($oldLogoPath)) {
            Storage::disk('public')->delete($oldLogoPath);
        }

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
    public function deleteApiKey(DeleteApiKeyRequest $request)
    {
        $validated = $request->validated();
        $landlordId = auth()->id();

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
