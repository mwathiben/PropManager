<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\LimitsPerPage;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    use LimitsPerPage;

    public function index(Request $request)
    {
        $user = $request->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $query = Invoice::where('landlord_id', $landlordId)
            ->with(['lease.unit.building', 'lease.tenant:id,name,email']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('building_id')) {
            $query->whereHas('lease.unit', fn ($q) => $q->where('building_id', $request->building_id));
        }

        // Phase-15 PERF-3: cap per_page at 200 to prevent ?per_page=99999 DoS.
        $invoices = $query->orderBy('created_at', 'desc')
            ->paginate($this->resolvePerPage($request, default: 20));

        return InvoiceResource::collection($invoices);
    }

    public function show(Request $request, Invoice $invoice)
    {
        $user = $request->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($invoice->landlord_id !== $landlordId) {
            abort(403);
        }

        $invoice->load(['lease.unit.building', 'lease.tenant', 'payments']);

        return new InvoiceResource($invoice);
    }
}
