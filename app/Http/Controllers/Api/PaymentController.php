<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $query = Payment::where('landlord_id', $landlordId)
            ->with(['invoice', 'lease.unit.building', 'lease.tenant:id,name,email']);

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }

        $payments = $query->orderBy('payment_date', 'desc')
            ->paginate($request->get('per_page', 20));

        return PaymentResource::collection($payments);
    }

    public function show(Request $request, Payment $payment)
    {
        $user = $request->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($payment->landlord_id !== $landlordId) {
            abort(403);
        }

        $payment->load(['invoice', 'lease.unit.building', 'lease.tenant']);

        return new PaymentResource($payment);
    }
}
