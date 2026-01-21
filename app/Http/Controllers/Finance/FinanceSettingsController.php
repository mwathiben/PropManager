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
use Barryvdh\DomPDF\Facade\Pdf;
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

    public function previewReceipt()
    {
        $landlordId = $this->getLandlordId();
        $user = User::find($landlordId);
        $settings = $user->getOrCreateInvoiceSetting();

        $samplePayment = (object) [
            'reference' => 'RCT-202601-0001',
            'payment_date' => now(),
            'payment_method' => 'mpesa',
            'amount' => 25000,
            'notes' => 'Sample payment for preview',
        ];

        $sampleInvoice = (object) [
            'invoice_number' => 'INV-202601-0001',
            'billing_period_start' => now()->startOfMonth(),
            'total_due' => 25000,
            'amount_paid' => 25000,
            'lease' => (object) [
                'tenant' => (object) [
                    'name' => 'John Doe',
                    'email' => 'johndoe@example.com',
                ],
                'unit' => (object) [
                    'unit_number' => 'A101',
                    'building' => (object) [
                        'name' => 'Sunrise Apartments',
                    ],
                ],
            ],
        ];

        $sampleReceipt = (object) [
            'receipt_number' => 'RCT-202601-0001',
        ];

        return Pdf::loadView('receipts.payment-receipt', [
            'payment' => $samplePayment,
            'invoice' => $sampleInvoice,
            'receipt' => $sampleReceipt,
            'settings' => $settings,
        ])->stream('receipt-preview.pdf');
    }
}
