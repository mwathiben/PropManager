<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateFiscalYearSettingsRequest;
use App\Http\Requests\UpdateInvoiceSettingsRequest;
use App\Http\Requests\UpdatePaymentMethodsRequest;
use App\Http\Requests\UpdateReceiptSettingsRequest;
use App\Http\Requests\UpdateReminderSettingsRequest;
use App\Http\Traits\WithFinanceRendering;
use App\Http\Traits\WithLandlordScope;
use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\FinanceSettingsService;
use App\Services\Payment\ReceiptGenerator;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class FinanceSettingsController extends Controller
{
    use WithFinanceRendering;
    use WithLandlordScope;

    public function __construct(
        protected FinanceSettingsService $settingsService,
    ) {}

    public function index(): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('settings', [
            'paymentConfig' => $this->settingsService->getPaymentConfig($landlordId),
            'paymentMethods' => PaymentConfiguration::getAvailablePaymentMethods(),
            'invoiceSettings' => $this->settingsService->getInvoiceSettings($landlordId),
            'reminderSettings' => $this->settingsService->getReminderSettings($landlordId),
            'receiptSettings' => $this->settingsService->getReceiptSettings($landlordId),
            'fiscalYearSettings' => $this->settingsService->getFiscalYearSettings($landlordId),
        ]);
    }

    public function updatePaymentMethods(UpdatePaymentMethodsRequest $request): RedirectResponse
    {
        $this->settingsService->updatePaymentMethods($this->getLandlordId(), $request);

        return back()->with('success', 'Payment methods saved successfully.');
    }

    public function updateInvoiceSettings(UpdateInvoiceSettingsRequest $request): RedirectResponse
    {
        $this->settingsService->updateInvoiceSettings($this->getLandlordId(), $request);

        return back()->with('success', 'Invoice settings saved successfully.');
    }

    public function updateReminderSettings(UpdateReminderSettingsRequest $request): RedirectResponse
    {
        $this->settingsService->updateReminderSettings($this->getLandlordId(), $request);

        return back()->with('success', 'Reminder settings saved successfully.');
    }

    public function updateReceiptSettings(UpdateReceiptSettingsRequest $request): RedirectResponse
    {
        $this->settingsService->updateReceiptSettings($this->getLandlordId(), $request);

        return back()->with('success', 'Receipt settings saved successfully.');
    }

    public function updateFiscalYearSettings(UpdateFiscalYearSettingsRequest $request): RedirectResponse
    {
        $this->settingsService->updateFiscalYearSettings($this->getLandlordId(), $request);

        return back()->with('success', 'Fiscal year settings saved successfully.');
    }

    public function previewReceipt(ReceiptGenerator $generator)
    {
        $user = User::find($this->getLandlordId());
        $settings = $user->getOrCreateInvoiceSetting();

        return $generator->preview($settings);
    }
}
