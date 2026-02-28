<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectPaymentVerificationRequest;
use App\Mail\PaymentVerificationApproved;
use App\Mail\PaymentVerificationRejected;
use App\Models\Document;
use App\Models\TenantPaymentVerification;
use App\Services\PaystackService;
use App\Traits\HasBuildingFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class TenantPaymentVerificationController extends Controller
{
    use HasBuildingFilter;

    public function showPaymentRequired()
    {
        $user = Auth::user();
        $lease = $user->lease;

        if (! $lease) {
            return redirect()->route('dashboard');
        }

        $verification = $lease->paymentVerification;

        if (! $verification) {
            return redirect()->route('dashboard');
        }

        if ($verification->isVerified()) {
            return redirect()->route('dashboard');
        }

        $verification->load('documents');

        return Inertia::render('Tenant/PaymentRequired', [
            'verification' => $verification,
            'lease' => $lease->load('unit.building'),
            'landlord' => $lease->landlord_id ? $verification->landlord : null,
        ]);
    }

    public function submitProofOfPayment(Request $request)
    {
        $user = Auth::user();
        $lease = $user->lease;

        if (! $lease) {
            return back()->with('error', 'No active lease found.');
        }

        $verification = $lease->paymentVerification;

        if (! $verification || $verification->isVerified()) {
            return back()->with('error', 'No pending verification found.');
        }

        $request->validate([
            'documents' => 'required|array|min:1',
            'documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        foreach ($request->file('documents') as $file) {
            $path = $file->store(
                "documents/{$verification->landlord_id}/payment_proofs",
                'private'
            );

            Document::create([
                'documentable_type' => TenantPaymentVerification::class,
                'documentable_id' => $verification->id,
                'landlord_id' => $verification->landlord_id,
                'title' => 'Payment Proof - '.$file->getClientOriginalName(),
                'document_type' => 'other',
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => $user->id,
                'uploaded_at' => now(),
            ]);
        }

        $verification->markAsSubmitted();

        return back()->with('success', 'Payment proof submitted successfully. Your landlord will review and verify your payment.');
    }

    public function payOnline(Request $request, PaystackService $paystackService)
    {
        $user = Auth::user();
        $lease = $user->lease;

        if (! $lease) {
            return back()->with('error', 'No active lease found.');
        }

        $verification = $lease->paymentVerification;

        if (! $verification || $verification->isVerified()) {
            return back()->with('error', 'No pending verification found.');
        }

        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $amount = min($request->amount, $verification->getOutstandingAmount());

        $result = $paystackService->initializeTransaction(
            $user->email,
            $amount,
            route('tenant.payment.callback'),
            [
                'verification_id' => $verification->id,
                'lease_id' => $lease->id,
                'type' => 'initial_payment',
            ]
        );

        if (! $result['status']) {
            return back()->with('error', 'Failed to initialize payment. Please try again.');
        }

        return Inertia::location($result['data']['authorization_url']);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', TenantPaymentVerification::class);

        $query = TenantPaymentVerification::query()
            ->with(['lease.unit.building', 'lease.tenant', 'documents']);

        $this->applyBuildingScope($query, $request);

        $verifications = $query
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, function ($q) use ($request) {
                $q->whereHas('lease.tenant', fn ($t) => $t->where('name', 'like', '%'.$request->search.'%'));
            })
            ->orderByRaw("CASE
                WHEN status = 'payment_submitted' THEN 1
                WHEN status = 'pending_payment' THEN 2
                WHEN status = 'rejected' THEN 3
                ELSE 4
            END")
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        return Inertia::render('PaymentVerifications/Index', [
            'verifications' => $verifications,
            'buildings' => $this->getBuildingsForFilter(),
            'filters' => $request->only(['status', 'search', 'building_id', 'wing_id']),
        ]);
    }

    public function show(TenantPaymentVerification $verification)
    {
        $this->authorize('view', $verification);

        $verification->load([
            'lease.unit.building',
            'lease.tenant',
            'documents',
            'verifiedBy',
        ]);

        return Inertia::render('PaymentVerifications/Show', [
            'verification' => $verification,
        ]);
    }

    public function approve(TenantPaymentVerification $verification)
    {
        $this->authorize('approve', $verification);

        $verification->load('lease.tenant');

        try {
            DB::transaction(fn () => $verification->approve(Auth::id()));
        } catch (\Exception $e) {
            Log::error('Payment verification approval failed', [
                'verification_id' => $verification->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to approve verification. Please try again.');
        }

        $tenant = $verification->lease->tenant;
        if ($tenant) {
            Mail::to($tenant)->queue(new PaymentVerificationApproved($verification));
        }

        return redirect()->route('payment-verifications.index')
            ->with('success', 'Payment verification approved. Tenant can now access the portal.');
    }

    public function reject(RejectPaymentVerificationRequest $request, TenantPaymentVerification $verification)
    {
        $verification->load('lease.tenant');

        try {
            DB::transaction(fn () => $verification->reject($request->validated('reason'), Auth::id()));
        } catch (\Exception $e) {
            Log::error('Payment verification rejection failed', [
                'verification_id' => $verification->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to reject verification. Please try again.');
        }

        $tenant = $verification->lease->tenant;
        if ($tenant) {
            Mail::to($tenant)->queue(new PaymentVerificationRejected($verification));
        }

        return redirect()->route('payment-verifications.index')
            ->with('success', 'Verification rejected. Tenant has been notified.');
    }

    private function applyBuildingScope($query, Request $request): void
    {
        $wingId = $request->filled('wing_id') ? (int) $request->wing_id : null;

        if ($wingId) {
            $query->whereHas('lease.unit', fn ($q) => $q->where('building_id', $wingId));

            return;
        }

        $buildingId = $request->filled('building_id') ? (int) $request->building_id : null;

        if ($buildingId) {
            $query->whereHas('lease.unit.building', fn ($b) => $b->where('property_id', $buildingId));
        }
    }
}
