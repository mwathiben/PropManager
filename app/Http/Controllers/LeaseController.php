<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustRentRequest;
use App\Http\Requests\BatchAdjustRentRequest;
use App\Http\Requests\Lease\WalletAdjustmentRequest;
use App\Http\Requests\StoreLeaseRequest;
use App\Mail\RentHikeNotice;
use App\Mail\TenantCredentials;
use App\Models\Lease;
use App\Models\RentHistory;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class LeaseController extends Controller
{
    /**
     * Display a listing of all leases (Lease Agreements archive).
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $query = Lease::where('landlord_id', $landlordId)
            ->with([
                'tenant:id,name,email,mobile_number',
                'unit.building:id,name',
                'documents' => fn ($q) => $q->where('document_type', 'lease_agreement'),
            ]);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('tenant', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('unit', function ($q) use ($search) {
                    $q->where('unit_number', 'like', "%{$search}%");
                });
            });
        }

        // Status filter
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'terminated') {
                $query->where('is_active', false);
            }
        }

        // Building filter
        if ($request->filled('building_id')) {
            $query->whereHas('unit', function ($q) use ($request) {
                $q->where('building_id', $request->building_id);
            });
        }

        $allowedSorts = ['created_at', 'start_date', 'end_date', 'rent_amount', 'deposit_amount'];
        $sortField = in_array($request->get('sort'), $allowedSorts, true) ? $request->get('sort') : 'created_at';
        $sortDirection = $request->get('direction') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortField, $sortDirection);

        $leases = $query->paginate(20)->withQueryString();

        // Calculate stats
        $stats = [
            'total_leases' => Lease::where('landlord_id', $landlordId)->count(),
            'active_leases' => Lease::where('landlord_id', $landlordId)->where('is_active', true)->count(),
            'terminated_leases' => Lease::where('landlord_id', $landlordId)->where('is_active', false)->count(),
        ];

        // Get buildings for filter dropdown
        $buildings = \App\Models\Building::where('landlord_id', $landlordId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Leases/Index', [
            'leases' => $leases,
            'stats' => $stats,
            'buildings' => $buildings,
            'filters' => $request->only(['search', 'status', 'building_id', 'sort', 'direction']),
        ]);
    }

    // Show the form to add a tenant (invitation-based)
    public function create(Unit $unit)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        return Inertia::render('Leases/Create', [
            'unit' => $unit,
            'smsConfigured' => TenantInvitationController::isSmsConfigured($landlordId),
            'whatsappConfigured' => TenantInvitationController::isWhatsAppConfigured($landlordId),
        ]);
    }

    public function store(StoreLeaseRequest $request, Unit $unit)
    {
        $docPath = null;
        if ($request->hasFile('lease_doc')) {
            $docPath = $request->file('lease_doc')->store('leases', 'local');
        }

        // PRIV-6: when a caretaker creates a lease, the new tenant +
        // lease must be keyed to the caretaker's LANDLORD, not to the
        // caretaker's user_id. auth()->user()->id is wrong for caretakers.
        $actor = auth()->user();
        $landlordId = $actor->isCaretaker() ? (int) $actor->landlord_id : (int) $actor->id;
        $temporaryPassword = Str::random(12);

        try {
            $result = DB::transaction(function () use ($request, $unit, $landlordId, $temporaryPassword, $docPath) {
                $tenant = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'mobile_number' => $request->phone,
                    'national_id' => $request->id_number,
                    'password' => Hash::make($temporaryPassword),
                ]);
                $tenant->role = 'tenant';
                $tenant->landlord_id = $landlordId;
                $tenant->save();

                $lease = Lease::create([
                    'unit_id' => $unit->id,
                    'tenant_id' => $tenant->id,
                    'landlord_id' => $landlordId,
                    'start_date' => $request->start_date,
                    'rent_amount' => $request->rent_amount,
                    'service_charge' => $request->service_charge ?? 0,
                    'deposit_amount' => $request->deposit_amount,
                    'lease_doc_path' => $docPath,
                    // wallet_balance defaults to 0 in the DB; explicit
                    // assignment dropped per MASS-1 ($fillable no longer
                    // exposes wallet_balance to mass assignment).
                    'is_active' => true,
                ]);

                $unit->update(['status' => 'occupied']);

                return compact('tenant', 'lease');
            });

            $result['lease']->load('unit.building.property');
            $landlord = User::find($landlordId);
            Mail::to($result['tenant'])->queue(new TenantCredentials(
                $result['tenant'],
                $result['lease'],
                $temporaryPassword,
                $landlord
            ));

            return redirect()->route('dashboard');
        } catch (\Exception $e) {
            if ($docPath) {
                Storage::tenant()->delete($docPath);
            }
            throw $e;
        }
    }

    public function adjustRent(AdjustRentRequest $request, Lease $lease)
    {
        // Store old amount before update
        $oldAmount = $lease->rent_amount;

        // 1. Log History
        RentHistory::create([
            'lease_id' => $lease->id,
            'old_amount' => $oldAmount,
            'new_amount' => $request->new_amount,
            'effective_date' => $request->effective_date,
            'reason' => $request->reason,
            'notification_sent' => true,
        ]);

        // 2. Update Lease
        $lease->update(['rent_amount' => $request->new_amount]);

        // PERF-Q4: queue, don't send. Synchronous SMTP added 200-2000ms to
        // every adjustRent request. batchAdjustRent already uses ->queue().
        $lease->load(['tenant', 'unit.building.property']);
        if ($lease->tenant) {
            Mail::to($lease->tenant)->queue(new RentHikeNotice(
                $lease,
                $oldAmount,
                $request->new_amount,
                $request->effective_date,
                $request->reason
            ));
        }

        return redirect()->back();
    }

    public function batchAdjustRent(BatchAdjustRentRequest $request)
    {
        $leases = Lease::whereIn('unit_id', $request->unit_ids)
            ->where('is_active', true)
            ->with(['tenant', 'unit.building.property'])
            ->get();

        foreach ($leases as $lease) {
            $oldAmount = $lease->rent_amount;
            $newAmount = $oldAmount;

            if ($request->adjustment_type === 'percentage') {
                // e.g. Increase by 10% -> 1.10
                $newAmount = $oldAmount * (1 + ($request->value / 100));
            } else {
                // Fixed amount increase
                $newAmount = $oldAmount + $request->value;
            }

            // Log History
            RentHistory::create([
                'lease_id' => $lease->id,
                'old_amount' => $oldAmount,
                'new_amount' => $newAmount,
                'effective_date' => $request->effective_date,
                'reason' => $request->reason,
                'notification_sent' => true,
            ]);

            // Update Lease
            $lease->update(['rent_amount' => $newAmount]);

            // Queue rent hike notification email (batch uses queue for performance)
            if ($lease->tenant) {
                Mail::to($lease->tenant)->queue(new RentHikeNotice(
                    $lease,
                    $oldAmount,
                    $newAmount,
                    $request->effective_date,
                    $request->reason
                ));
            }
        }

        return redirect()->back()->with('success', 'Rent updated for '.count($leases).' units.');
    }

    public function walletAdjustment(WalletAdjustmentRequest $request, Lease $lease)
    {
        // PRIV-1: WalletAdjustmentRequest::authorize() now scopes by
        // landlord ownership. The DB::transaction wrap is required because
        // Lease::creditToWallet/deductFromWallet (CONC-13) throw_unless()
        // an outer transaction is active.
        $validated = $request->validated();
        $amount = (float) $validated['amount'];

        if ($validated['type'] === 'debit' && $amount > $lease->wallet_balance) {
            return back()->withErrors(['amount' => 'Cannot debit more than current wallet balance.']);
        }

        DB::transaction(function () use ($lease, $validated, $amount) {
            if ($validated['type'] === 'credit') {
                $lease->creditToWallet($amount, $validated['reason']);
            } else {
                $lease->deductFromWallet($amount, $validated['reason']);
            }
        });

        $verb = $validated['type'] === 'credit' ? 'credited to' : 'debited from';
        $currency = $lease->unit?->building?->getEffectiveCurrency()->symbol() ?? 'KES';

        return back()->with('success', "{$currency} ".number_format($amount, 2)." {$verb} wallet.");
    }

    public function walletHistory(Lease $lease)
    {
        // PRIV-1: scope-check the route-bound lease on the read path too.
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? (int) $user->landlord_id : (int) $user->id;
        if ((int) $lease->landlord_id !== $landlordId) {
            abort(403);
        }

        $transactions = $lease->walletTransactions()
            ->with(['invoice', 'payment'])
            ->paginate(20);

        return Inertia::render('Leases/WalletHistory', [
            'lease' => $lease->load(['tenant', 'unit.building']),
            'transactions' => $transactions,
        ]);
    }

    public function show(Lease $lease)
    {
        return redirect()->route('tenants.show', $lease->tenant_id);
    }

    /**
     * Phase-61 TERMINATION-3: early termination route. Either party
     * may initiate; the other party acknowledges or disputes. Notice
     * period validated via NoticePeriodValidator.
     */
    public function terminate(
        \Illuminate\Http\Request $request,
        Lease $lease,
        \App\Services\Lease\LeaseTerminationService $service,
    ) {
        $validated = $request->validate([
            'termination_reason' => 'required|in:breach_by_tenant,breach_by_landlord,mutual,hardship,sale,other',
            'termination_date' => 'required|date|after:today',
            'reason_text' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        if ($user->id !== $lease->landlord_id && $user->id !== $lease->tenant_id) {
            abort(403);
        }

        try {
            $service->initiate($lease, $user, $validated);
        } catch (\App\Exceptions\ShortNoticeException $e) {
            return back()->with('error', __($e->translationKey(), ['days' => $e->requiredDays]));
        }

        return back()->with('success', __('lease.termination_initiated'));
    }

    /**
     * Phase-61 TRANSFER-3: tenant assigns lease to another tenant.
     * Outgoing tenant initiates; landlord approves; landlord completes
     * (which swaps Lease.tenant_id).
     */
    public function transfer(
        \Illuminate\Http\Request $request,
        Lease $lease,
        \App\Services\Lease\LeaseTransferService $service,
    ) {
        $validated = $request->validate([
            'incoming_tenant_email' => 'required|email|exists:users,email',
            'transfer_date' => 'required|date|after:today',
            'transfer_fee_amount' => 'nullable|numeric|min:0',
            'reason_text' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        if ($user->id !== $lease->tenant_id) {
            abort(403);
        }

        $incoming = \App\Models\User::where('email', $validated['incoming_tenant_email'])->firstOrFail();

        try {
            $service->request($lease, $user, $incoming, $validated);
        } catch (\App\Exceptions\ShortNoticeException $e) {
            return back()->with('error', __($e->translationKey(), ['days' => $e->requiredDays]));
        }

        return back()->with('success', __('lease.transfer_requested'));
    }

    /**
     * Phase-61 PAUSE-3: start a temporary lease pause. Landlord-only.
     */
    public function pause(
        \Illuminate\Http\Request $request,
        Lease $lease,
        \App\Services\Lease\LeasePauseService $service,
    ) {
        $validated = $request->validate([
            'pause_start' => 'required|date|after:today',
            'pause_end' => 'required|date|after:pause_start',
            'reason' => 'required|in:tenant_hardship,landlord_renovation,mutual,other',
            'reason_text' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        if ($user->id !== $lease->landlord_id) {
            abort(403);
        }

        try {
            $service->start($lease, $user, $validated);
        } catch (\App\Exceptions\ShortNoticeException $e) {
            return back()->with('error', __($e->translationKey(), ['days' => $e->requiredDays]));
        }

        return back()->with('success', __('lease.pause_started'));
    }

    public function download(Lease $lease)
    {
        if (! $lease->lease_doc_path || ! Storage::tenant()->exists($lease->lease_doc_path)) {
            abort(404, 'Lease document not found.');
        }

        // Phase-59 SIGNED-URLS-2: 302 to short-lived signed URL.
        return redirect()->away(
            app(\App\Services\Storage\TenantDiskResolver::class)->temporaryUrl(
                $lease->lease_doc_path,
                $lease->landlord_id,
                5,
                'lease-agreement-'.$lease->id.'.pdf',
            ),
        );
    }
}
