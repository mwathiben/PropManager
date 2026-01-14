<?php

namespace App\Http\Controllers;

use App\Models\ReceiptTemplate;
use App\Services\PaymentQrCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ReceiptTemplateController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', ReceiptTemplate::class);

        $templates = ReceiptTemplate::where('landlord_id', Auth::id())
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('ReceiptTemplates/Index', [
            'templates' => $templates,
            'designOptions' => ReceiptTemplate::getDesignOptions(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', ReceiptTemplate::class);

        $user = Auth::user();
        $settings = $user->invoiceSetting;

        return Inertia::render('ReceiptTemplates/Edit', [
            'template' => null,
            'designOptions' => ReceiptTemplate::getDesignOptions(),
            'toggleGroups' => ReceiptTemplate::getToggleGroups(),
            'settings' => $settings,
            'sampleReceipt' => $this->getSampleReceiptData($settings),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', ReceiptTemplate::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'design' => 'required|string|in:classic,modern,minimal,professional',
            'is_default' => 'boolean',
            'show_logo' => 'boolean',
            'show_receipt_number' => 'boolean',
            'show_payment_date' => 'boolean',
            'show_payment_method' => 'boolean',
            'show_transaction_reference' => 'boolean',
            'show_amount_breakdown' => 'boolean',
            'show_tenant_name' => 'boolean',
            'show_tenant_email' => 'boolean',
            'show_tenant_phone' => 'boolean',
            'show_unit_details' => 'boolean',
            'show_building_name' => 'boolean',
            'show_invoice_details' => 'boolean',
            'show_invoice_breakdown' => 'boolean',
            'show_balance_after_payment' => 'boolean',
            'show_thank_you_message' => 'boolean',
            'show_qr_code' => 'boolean',
            'show_footer' => 'boolean',
            'custom_header' => 'nullable|string|max:1000',
            'custom_footer' => 'nullable|string|max:1000',
            'thank_you_message' => 'nullable|string|max:500',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
        ]);

        $validated['landlord_id'] = Auth::id();

        $template = ReceiptTemplate::create($validated);

        if ($validated['is_default'] ?? false) {
            $template->makeDefault();
        }

        return redirect()->route('finances.templates.receipts')
            ->with('success', 'Receipt template created successfully.');
    }

    public function edit(ReceiptTemplate $receiptTemplate)
    {
        $this->authorize('update', $receiptTemplate);

        $user = Auth::user();
        $settings = $user->invoiceSetting;

        return Inertia::render('ReceiptTemplates/Edit', [
            'template' => $receiptTemplate,
            'designOptions' => ReceiptTemplate::getDesignOptions(),
            'toggleGroups' => ReceiptTemplate::getToggleGroups(),
            'settings' => $settings,
            'sampleReceipt' => $this->getSampleReceiptData($settings),
        ]);
    }

    public function update(Request $request, ReceiptTemplate $receiptTemplate)
    {
        $this->authorize('update', $receiptTemplate);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'design' => 'required|string|in:classic,modern,minimal,professional',
            'is_default' => 'boolean',
            'show_logo' => 'boolean',
            'show_receipt_number' => 'boolean',
            'show_payment_date' => 'boolean',
            'show_payment_method' => 'boolean',
            'show_transaction_reference' => 'boolean',
            'show_amount_breakdown' => 'boolean',
            'show_tenant_name' => 'boolean',
            'show_tenant_email' => 'boolean',
            'show_tenant_phone' => 'boolean',
            'show_unit_details' => 'boolean',
            'show_building_name' => 'boolean',
            'show_invoice_details' => 'boolean',
            'show_invoice_breakdown' => 'boolean',
            'show_balance_after_payment' => 'boolean',
            'show_thank_you_message' => 'boolean',
            'show_qr_code' => 'boolean',
            'show_footer' => 'boolean',
            'custom_header' => 'nullable|string|max:1000',
            'custom_footer' => 'nullable|string|max:1000',
            'thank_you_message' => 'nullable|string|max:500',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
        ]);

        $receiptTemplate->update($validated);

        if ($validated['is_default'] ?? false) {
            $receiptTemplate->makeDefault();
        }

        return redirect()->route('finances.templates.receipts')
            ->with('success', 'Receipt template updated successfully.');
    }

    public function destroy(ReceiptTemplate $receiptTemplate)
    {
        $this->authorize('delete', $receiptTemplate);

        if ($receiptTemplate->is_default) {
            return back()->with('error', 'Cannot delete the default template.');
        }

        if ($receiptTemplate->receipts()->exists()) {
            return back()->with('error', 'Cannot delete a template that has been used for receipts.');
        }

        $receiptTemplate->delete();

        return redirect()->route('finances.templates.receipts')
            ->with('success', 'Receipt template deleted successfully.');
    }

    public function setDefault(ReceiptTemplate $receiptTemplate)
    {
        $this->authorize('update', $receiptTemplate);

        $receiptTemplate->makeDefault();

        return back()->with('success', 'Default template updated successfully.');
    }

    private function getSampleReceiptData($settings): array
    {
        $qrService = app(PaymentQrCodeService::class);
        $qrData = implode("\n", [
            'PAYMENT RECEIPT',
            'Receipt: RCP-'.now()->format('Ym').'-0001',
            'Amount: KES 15,000.00',
            'Date: '.now()->format('Y-m-d'),
            'Method: M-Pesa',
            'Reference: QJK7H4M3X2',
            'Tenant: John Doe',
        ]);

        return [
            'receipt_number' => 'RCP-'.now()->format('Ym').'-0001',
            'payment_date' => now()->format('M d, Y'),
            'payment_time' => now()->format('h:i A'),
            'payment_method' => 'M-Pesa',
            'transaction_reference' => 'QJK7H4M3X2',
            'tenant' => [
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'phone' => '+254 712 345 678',
            ],
            'unit' => [
                'name' => 'Unit A-101',
                'building' => 'Sunset Apartments',
            ],
            'invoice' => [
                'number' => 'INV-'.now()->format('Ym').'-0042',
                'total_amount' => 32250,
                'items' => [
                    ['description' => 'Monthly Rent - January 2026', 'amount' => 25000],
                    ['description' => 'Water Charges', 'amount' => 2250],
                    ['description' => 'Previous Balance', 'amount' => 5000],
                ],
            ],
            'payment' => [
                'amount' => 15000,
                'previous_balance' => 32250,
                'new_balance' => 17250,
            ],
            'business' => [
                'name' => $settings->business_name ?? 'Your Business Name',
                'address' => $settings->business_address ?? '123 Business St, Nairobi',
                'phone' => $settings->business_phone ?? '+254 700 000 000',
            ],
            'qr_code' => $qrService->generateBase64QrCode($qrData, ['size' => 150]),
        ];
    }
}
