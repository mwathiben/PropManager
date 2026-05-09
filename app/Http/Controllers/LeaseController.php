<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustRentRequest;
use App\Http\Requests\BatchAdjustRentRequest;
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

        $landlord = auth()->user();
        $temporaryPassword = Str::random(12);

        try {
            $result = DB::transaction(function () use ($request, $unit, $landlord, $temporaryPassword, $docPath) {
                $tenant = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'mobile_number' => $request->phone,
                    'national_id' => $request->id_number,
                    'password' => Hash::make($temporaryPassword),
                ]);
                $tenant->role = 'tenant';
                $tenant->landlord_id = $landlord->id;
                $tenant->save();

                $lease = Lease::create([
                    'unit_id' => $unit->id,
                    'tenant_id' => $tenant->id,
                    'landlord_id' => $landlord->id,
                    'start_date' => $request->start_date,
                    'rent_amount' => $request->rent_amount,
                    'service_charge' => $request->service_charge ?? 0,
                    'deposit_amount' => $request->deposit_amount,
                    'lease_doc_path' => $docPath,
                    'wallet_balance' => 0,
                    'is_active' => true,
                ]);

                $unit->update(['status' => 'occupied']);

                return compact('tenant', 'lease');
            });

            $result['lease']->load('unit.building.property');
            Mail::to($result['tenant'])->queue(new TenantCredentials(
                $result['tenant'],
                $result['lease'],
                $temporaryPassword,
                $landlord
            ));

            return redirect()->route('dashboard');
        } catch (\Exception $e) {
            if ($docPath) {
                Storage::disk('local')->delete($docPath);
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

    public function walletAdjustment(Request $request, Lease $lease)
    {
        $request->validate([
            'type' => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
        ]);

        $amount = $request->amount;

        if ($request->type === 'credit') {
            $lease->creditToWallet($amount, $request->reason);
            $message = 'KES '.number_format($amount, 2).' credited to wallet.';
        } else {
            if ($amount > $lease->wallet_balance) {
                return back()->withErrors(['amount' => 'Cannot debit more than current wallet balance.']);
            }
            $lease->deductFromWallet($amount, $request->reason);
            $message = 'KES '.number_format($amount, 2).' debited from wallet.';
        }

        return back()->with('success', $message);
    }

    public function walletHistory(Lease $lease)
    {
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

    public function download(Lease $lease)
    {
        if (! $lease->lease_doc_path || ! Storage::disk('local')->exists($lease->lease_doc_path)) {
            abort(404, 'Lease document not found.');
        }

        return Storage::disk('local')->download(
            $lease->lease_doc_path,
            'lease-agreement-'.$lease->id.'.pdf'
        );
    }
}
