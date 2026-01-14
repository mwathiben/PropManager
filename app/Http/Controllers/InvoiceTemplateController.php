<?php

namespace App\Http\Controllers;

use App\Models\InvoiceTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class InvoiceTemplateController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', InvoiceTemplate::class);

        $templates = InvoiceTemplate::where('landlord_id', Auth::id())
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('InvoiceTemplates/Index', [
            'templates' => $templates,
            'designOptions' => InvoiceTemplate::getDesignOptions(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', InvoiceTemplate::class);

        $user = Auth::user();
        $settings = $user->invoiceSetting;

        return Inertia::render('InvoiceTemplates/Edit', [
            'template' => null,
            'designOptions' => InvoiceTemplate::getDesignOptions(),
            'settings' => $settings,
            'sampleInvoice' => $this->getSampleInvoiceData($settings),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', InvoiceTemplate::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'design' => 'required|string|in:classic,modern,minimal,professional',
            'is_default' => 'boolean',
            'show_logo' => 'boolean',
            'show_tax_number' => 'boolean',
            'show_tenant_id' => 'boolean',
            'show_unit_details' => 'boolean',
            'show_lease_reference' => 'boolean',
            'show_due_date' => 'boolean',
            'show_late_warning' => 'boolean',
            'show_bank_details' => 'boolean',
            'show_footer' => 'boolean',
            'show_qr_code' => 'boolean',
            'show_payment_instructions' => 'boolean',
            'show_arrears_breakdown' => 'boolean',
            'show_water_details' => 'boolean',
            'custom_header' => 'nullable|string|max:1000',
            'custom_footer' => 'nullable|string|max:1000',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
        ]);

        $validated['landlord_id'] = Auth::id();

        $template = InvoiceTemplate::create($validated);

        if ($validated['is_default'] ?? false) {
            $template->makeDefault();
        }

        return redirect()->route('finances.templates.invoices')
            ->with('success', 'Invoice template created successfully.');
    }

    public function edit(InvoiceTemplate $invoiceTemplate)
    {
        $this->authorize('update', $invoiceTemplate);

        $user = Auth::user();
        $settings = $user->invoiceSetting;

        return Inertia::render('InvoiceTemplates/Edit', [
            'template' => $invoiceTemplate,
            'designOptions' => InvoiceTemplate::getDesignOptions(),
            'settings' => $settings,
            'sampleInvoice' => $this->getSampleInvoiceData($settings),
        ]);
    }

    public function update(Request $request, InvoiceTemplate $invoiceTemplate)
    {
        $this->authorize('update', $invoiceTemplate);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'design' => 'required|string|in:classic,modern,minimal,professional',
            'is_default' => 'boolean',
            'show_logo' => 'boolean',
            'show_tax_number' => 'boolean',
            'show_tenant_id' => 'boolean',
            'show_unit_details' => 'boolean',
            'show_lease_reference' => 'boolean',
            'show_due_date' => 'boolean',
            'show_late_warning' => 'boolean',
            'show_bank_details' => 'boolean',
            'show_footer' => 'boolean',
            'show_qr_code' => 'boolean',
            'show_payment_instructions' => 'boolean',
            'show_arrears_breakdown' => 'boolean',
            'show_water_details' => 'boolean',
            'custom_header' => 'nullable|string|max:1000',
            'custom_footer' => 'nullable|string|max:1000',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
        ]);

        $invoiceTemplate->update($validated);

        if ($validated['is_default'] ?? false) {
            $invoiceTemplate->makeDefault();
        }

        return redirect()->route('finances.templates.invoices')
            ->with('success', 'Invoice template updated successfully.');
    }

    public function destroy(InvoiceTemplate $invoiceTemplate)
    {
        $this->authorize('delete', $invoiceTemplate);

        if ($invoiceTemplate->is_default) {
            return back()->with('error', 'Cannot delete the default template.');
        }

        if ($invoiceTemplate->invoices()->exists()) {
            return back()->with('error', 'Cannot delete a template that has been used for invoices.');
        }

        $invoiceTemplate->delete();

        return redirect()->route('finances.templates.invoices')
            ->with('success', 'Invoice template deleted successfully.');
    }

    public function setDefault(InvoiceTemplate $invoiceTemplate)
    {
        $this->authorize('update', $invoiceTemplate);

        $invoiceTemplate->makeDefault();

        return back()->with('success', 'Default template updated successfully.');
    }

    private function getSampleInvoiceData($settings): array
    {
        return [
            'invoice_number' => ($settings->invoice_prefix ?? 'INV').'-'.now()->format('Ym').'-0001',
            'date' => now()->format('M d, Y'),
            'due_date' => now()->addDays($settings->default_due_days ?? 5)->format('M d, Y'),
            'tenant' => [
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'phone' => '+254 712 345 678',
                'national_id' => '12345678',
            ],
            'unit' => [
                'name' => 'Unit A-101',
                'building' => 'Sunset Apartments',
                'property' => 'Sunset Properties',
            ],
            'lease' => [
                'reference' => 'LSE-2026-0001',
                'start_date' => 'Jan 01, 2026',
                'rent_amount' => 25000,
            ],
            'items' => [
                ['description' => 'Monthly Rent - January 2026', 'quantity' => 1, 'unit_price' => 25000, 'total' => 25000],
                ['description' => 'Water Charges (15 units @ 150/unit)', 'quantity' => 15, 'unit_price' => 150, 'total' => 2250],
                ['description' => 'Previous Balance', 'quantity' => 1, 'unit_price' => 5000, 'total' => 5000],
            ],
            'subtotal' => 32250,
            'late_fee' => 0,
            'total_due' => 32250,
            'amount_paid' => 0,
            'balance_due' => 32250,
            'late_warning' => 'A late fee of '.($settings->late_penalty_percentage ?? 5).'% will be applied after '.($settings->grace_period_days ?? 3).' days past due date.',
            'terms' => $settings->terms_and_conditions ?? 'Payment is due by the date specified above. Please include the invoice number as reference when making payment.',
            'footer' => $settings->footer_note ?? 'Thank you for your prompt payment.',
        ];
    }
}
