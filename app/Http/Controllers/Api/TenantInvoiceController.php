<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class TenantInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $invoices = Invoice::whereHas('lease', function ($query) use ($user) {
            $query->where('tenant_id', $user->id);
        })
            ->with(['lease.unit.building'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return InvoiceResource::collection($invoices);
    }

    public function show(Request $request, Invoice $invoice)
    {
        $user = $request->user();

        if ($invoice->lease->tenant_id !== $user->id) {
            abort(403, 'You do not have access to this invoice.');
        }

        $invoice->load(['lease.unit.building', 'payments']);

        return new InvoiceResource($invoice);
    }

    public function download(Request $request, Invoice $invoice)
    {
        $user = $request->user();

        if ($invoice->lease->tenant_id !== $user->id) {
            abort(403, 'You do not have access to this invoice.');
        }

        $invoice->load(['lease.tenant', 'lease.unit.building.property', 'payments']);

        $tenant = $invoice->lease->tenant;
        $unit = $invoice->lease->unit;
        $building = $unit->building;
        $property = $building->property;

        $pdf = Pdf::loadView('invoices.invoice-pdf', [
            'invoice' => $invoice,
            'tenant' => $tenant,
            'unit' => $unit,
            'building' => $building,
            'property' => $property,
        ]);

        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }
}
