<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoicePdfService
{
    public function generatePdf(Invoice $invoice, ?InvoiceTemplate $template = null): \Barryvdh\DomPDF\PDF
    {
        $template = $template ?? $invoice->template ?? $this->getDefaultTemplate($invoice);
        $settings = $invoice->lease->unit->building->property->landlord->invoiceSetting;

        $data = $this->prepareInvoiceData($invoice, $template, $settings);

        return Pdf::loadView('invoices.pdf', $data)
            ->setPaper('a4', 'portrait');
    }

    public function savePdf(Invoice $invoice, ?InvoiceTemplate $template = null): string
    {
        $pdf = $this->generatePdf($invoice, $template);
        $path = "invoices/{$invoice->landlord_id}/{$invoice->invoice_number}.pdf";

        Storage::disk('private')->put($path, $pdf->output());

        return $path;
    }

    public function streamPdf(Invoice $invoice, ?InvoiceTemplate $template = null)
    {
        $pdf = $this->generatePdf($invoice, $template);

        return $pdf->stream("{$invoice->invoice_number}.pdf");
    }

    public function downloadPdf(Invoice $invoice, ?InvoiceTemplate $template = null)
    {
        $pdf = $this->generatePdf($invoice, $template);

        return $pdf->download("{$invoice->invoice_number}.pdf");
    }

    protected function getDefaultTemplate(Invoice $invoice): ?InvoiceTemplate
    {
        $landlordId = $invoice->lease->unit->building->property->landlord_id;

        return InvoiceTemplate::where('landlord_id', $landlordId)
            ->where('is_default', true)
            ->first();
    }

    protected function prepareInvoiceData(Invoice $invoice, ?InvoiceTemplate $template, $settings): array
    {
        $lease = $invoice->lease;
        $tenant = $lease->tenant;
        $unit = $lease->unit;
        $building = $unit->building;
        $property = $building->property;

        $items = $this->buildInvoiceItems($invoice);

        return [
            'invoice' => $invoice,
            'template' => $template,
            'settings' => $settings,
            'tenant' => [
                'name' => $tenant->name,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'national_id' => $tenant->national_id,
            ],
            'unit' => [
                'name' => $unit->unit_number,
                'building' => $building->name,
                'property' => $property->name,
            ],
            'lease' => [
                'reference' => 'LSE-'.str_pad($lease->id, 4, '0', STR_PAD_LEFT),
                'start_date' => $lease->start_date?->format('M d, Y'),
                'rent_amount' => $lease->rent_amount,
            ],
            'items' => $items,
            'subtotal' => $invoice->rent_due + $invoice->water_due + $invoice->arrears,
            'late_fees' => $invoice->late_fees_total ?? 0,
            'wallet_applied' => $invoice->wallet_applied ?? 0,
            'total_due' => $invoice->total_due,
            'amount_paid' => $invoice->amount_paid,
            'balance_due' => $invoice->total_due - $invoice->amount_paid,
            'logoUrl' => $settings?->logo_path ? Storage::url($settings->logo_path) : null,
        ];
    }

    protected function buildInvoiceItems(Invoice $invoice): array
    {
        $items = [];

        if ($invoice->rent_due > 0) {
            $period = $invoice->billing_period_start?->format('F Y') ?? 'Current Period';
            $items[] = [
                'description' => "Monthly Rent - {$period}",
                'quantity' => 1,
                'unit_price' => $invoice->rent_due,
                'total' => $invoice->rent_due,
            ];
        }

        if ($invoice->water_due > 0) {
            $items[] = [
                'description' => 'Water Charges',
                'quantity' => 1,
                'unit_price' => $invoice->water_due,
                'total' => $invoice->water_due,
            ];
        }

        if ($invoice->arrears > 0) {
            $items[] = [
                'description' => 'Previous Balance',
                'quantity' => 1,
                'unit_price' => $invoice->arrears,
                'total' => $invoice->arrears,
            ];
        }

        if (($invoice->late_fees_total ?? 0) > 0) {
            $items[] = [
                'description' => 'Late Payment Fee',
                'quantity' => 1,
                'unit_price' => $invoice->late_fees_total,
                'total' => $invoice->late_fees_total,
            ];
        }

        foreach ($invoice->items as $item) {
            $items[] = [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
            ];
        }

        return $items;
    }
}
