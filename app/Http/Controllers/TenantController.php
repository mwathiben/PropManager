<?php

namespace App\Http\Controllers;

use App\Models\CreditNote;
use App\Models\EmergencyContact;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\TenantActivity;
use App\Models\TenantInvitation;
use App\Models\TenantNote;
use App\Models\User;
use App\Models\VerificationTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class TenantController extends Controller
{
    /**
     * Display a listing of all tenants for the landlord with tabs.
     * Tab 1: Active Tenants (with active leases)
     * Tab 2: Pending Invitations (pending tenant invitations)
     * Tab 3: Past Tenants (inactive/terminated leases)
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Only landlords and caretakers can access
        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;
        $tab = $request->get('tab', 'active');
        $search = $request->get('search', '');

        // Base query for tenants
        $tenantsQuery = User::where('role', 'tenant')
            ->where('landlord_id', $landlordId)
            ->when($search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%");
                });
            });

        // Tab-specific data
        $activeTenants = null;
        $pastTenants = null;
        $pendingInvitations = null;

        if ($tab === 'active') {
            $activeTenants = (clone $tenantsQuery)
                ->whereHas('leases', fn ($q) => $q->where('is_active', true))
                ->with([
                    'leases' => fn ($q) => $q->where('is_active', true)->with('unit.building.property'),
                    'emergencyContacts',
                ])
                ->withCount(['tenantNotes', 'activities'])
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString();
        } elseif ($tab === 'past') {
            $pastTenants = (clone $tenantsQuery)
                ->whereDoesntHave('leases', fn ($q) => $q->where('is_active', true))
                ->whereHas('leases') // Must have had a lease before
                ->with([
                    'leases' => fn ($q) => $q->where('is_active', false)
                        ->orderBy('end_date', 'desc')
                        ->with('unit.building.property'),
                ])
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString();
        } elseif ($tab === 'pending') {
            $pendingInvitations = TenantInvitation::where('landlord_id', $landlordId)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->when($search, function ($q, $search) {
                    $q->where(function ($q) use ($search) {
                        $q->where('email', 'like', "%{$search}%")
                            ->orWhere('tenant_name', 'like', "%{$search}%")
                            ->orWhere('tenant_phone', 'like', "%{$search}%");
                    });
                })
                ->with('unit.building.property')
                ->orderBy('created_at', 'desc')
                ->paginate(20)
                ->withQueryString();
        }

        // Get counts for badges
        $counts = [
            'active' => User::where('role', 'tenant')
                ->where('landlord_id', $landlordId)
                ->whereHas('leases', fn ($q) => $q->where('is_active', true))
                ->count(),
            'pending' => TenantInvitation::where('landlord_id', $landlordId)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->count(),
            'past' => User::where('role', 'tenant')
                ->where('landlord_id', $landlordId)
                ->whereDoesntHave('leases', fn ($q) => $q->where('is_active', true))
                ->whereHas('leases')
                ->count(),
        ];

        // Summary stats
        $stats = [
            'totalTenants' => $counts['active'] + $counts['past'],
            'activeTenants' => $counts['active'],
            'pendingInvitations' => $counts['pending'],
            'withArrears' => User::where('role', 'tenant')
                ->where('landlord_id', $landlordId)
                ->whereHas('leases', fn ($q) => $q->where('is_active', true)->where('arrears', '>', 0))
                ->count(),
            'totalMonthlyRent' => Lease::where('landlord_id', $landlordId)
                ->where('is_active', true)
                ->sum('rent_amount'),
            'totalArrears' => Lease::where('landlord_id', $landlordId)
                ->where('is_active', true)
                ->sum('arrears'),
        ];

        return Inertia::render('Tenants/Index', [
            'activeTenants' => $activeTenants,
            'pastTenants' => $pastTenants,
            'pendingInvitations' => $pendingInvitations,
            'tab' => $tab,
            'counts' => $counts,
            'stats' => $stats,
            'filters' => $request->only(['search', 'tab']),
        ]);
    }

    /**
     * Return comprehensive tenant data for modal display.
     * Used by Dashboard and other views that need tenant details via AJAX.
     */
    public function modalData(User $tenant)
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403);
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;
        if ($tenant->landlord_id !== $landlordId) {
            abort(403);
        }

        $tenant->load([
            'leases' => fn ($q) => $q->with([
                'unit.building.property',
                'verifications',
                'rentHistory' => fn ($q) => $q->orderBy('effective_date', 'desc'),
            ])->orderBy('is_active', 'desc')->orderBy('created_at', 'desc'),
            'emergencyContacts',
            'tenantNotes' => fn ($q) => $q->with('author')->orderBy('is_pinned', 'desc')->orderBy('created_at', 'desc'),
            'activities' => fn ($q) => $q->with('performer')->orderBy('created_at', 'desc')->limit(50),
        ]);

        $activeLease = $tenant->leases->firstWhere('is_active', true);
        $pastLeases = $tenant->leases->where('is_active', false)->values();

        $payments = [];
        $invoices = [];
        $financialSummary = [
            'total_paid' => 0,
            'total_invoiced' => 0,
            'outstanding' => 0,
            'wallet_balance' => 0,
            'deposit_held' => 0,
        ];

        if ($activeLease) {
            $payments = Payment::where('lease_id', $activeLease->id)
                ->with('invoice:id,invoice_number')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            $invoices = Invoice::where('lease_id', $activeLease->id)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            $totalPaid = Payment::where('lease_id', $activeLease->id)->sum('amount');
            $totalInvoiced = Invoice::where('lease_id', $activeLease->id)->sum('total_due');

            $financialSummary = [
                'total_paid' => $totalPaid,
                'total_invoiced' => $totalInvoiced,
                'outstanding' => max(0, $totalInvoiced - $totalPaid),
                'wallet_balance' => $activeLease->wallet_balance ?? 0,
                'deposit_held' => $activeLease->deposit_amount ?? 0,
            ];
        }

        $tenantDocuments = \App\Models\Document::where('documentable_type', 'App\\Models\\User')
            ->where('documentable_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $leaseDocuments = $activeLease
            ? \App\Models\Document::where('documentable_type', 'App\\Models\\Lease')
                ->where('documentable_id', $activeLease->id)
                ->orderBy('created_at', 'desc')
                ->get()
            : collect();

        $documents = $tenantDocuments->merge($leaseDocuments)->sortByDesc('created_at')->values();

        $verificationStatus = [
            'kyc_completed' => $tenant->hasCompletedKyc(),
            'kyc_completed_at' => $tenant->kyc_completed_at,
            'lease_verified' => $activeLease?->verifications->every(fn ($v) => $v->is_verified) ?? false,
            'verification_count' => $activeLease?->verifications->count() ?? 0,
            'verified_count' => $activeLease?->verifications->where('is_verified', true)->count() ?? 0,
        ];

        return response()->json([
            'tenant' => $tenant->only([
                'id', 'name', 'email', 'mobile_number', 'national_id',
                'profile_photo_path', 'kyc_completed_at', 'created_at',
                'occupation', 'employer', 'monthly_income',
            ]),
            'activeLease' => $activeLease,
            'pastLeases' => $pastLeases,
            'financialSummary' => $financialSummary,
            'documents' => $documents,
            'payments' => $payments,
            'invoices' => $invoices,
            'emergencyContacts' => $tenant->emergencyContacts,
            'tenantNotes' => $tenant->tenantNotes,
            'verificationStatus' => $verificationStatus,
            'activities' => $tenant->activities,
        ]);
    }

    /**
     * Display detailed tenant profile.
     */
    public function show(User $tenant)
    {
        $user = auth()->user();

        // Authorization: landlord or caretaker must own this tenant
        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403);
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;
        if ($tenant->landlord_id !== $landlordId) {
            abort(403);
        }

        // Load tenant with all relationships
        $tenant->load([
            'leases' => fn ($q) => $q->with([
                'unit.building.property',
                'verifications',
                'moveOut.deductions',
                'rentHistory' => fn ($q) => $q->orderBy('effective_date', 'desc'),
                'documents',
            ])->orderBy('is_active', 'desc')->orderBy('created_at', 'desc'),
            'emergencyContacts',
            'tenantNotes' => fn ($q) => $q->with('author')->orderBy('created_at', 'desc'),
            'activities' => fn ($q) => $q->with('performer')->orderBy('created_at', 'desc')->limit(50),
            'documents',
        ]);

        // Get active lease
        $activeLease = $tenant->leases->firstWhere('is_active', true);

        // Payment history
        $payments = [];
        $invoices = [];
        if ($activeLease) {
            $payments = Payment::where('lease_id', $activeLease->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $invoices = Invoice::where('lease_id', $activeLease->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        }

        // Verification templates for this landlord
        $verificationTemplates = VerificationTemplate::where('landlord_id', $landlordId)
            ->with('items')
            ->get();

        // Merge tenant and lease documents
        $tenantDocuments = $tenant->documents ?? collect();
        $leaseDocuments = $activeLease?->documents ?? collect();
        $allDocuments = $tenantDocuments->merge($leaseDocuments)->sortByDesc('created_at')->values();

        // Make national_id visible for landlord view
        $tenant->makeVisible(['national_id']);

        return Inertia::render('Tenants/Show', [
            'tenant' => $tenant,
            'activeLease' => $activeLease,
            'payments' => $payments,
            'invoices' => $invoices,
            'verificationTemplates' => $verificationTemplates,
            'documents' => $allDocuments,
        ]);
    }

    /**
     * Update tenant details.
     */
    public function update(Request $request, User $tenant)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($tenant->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$tenant->id,
            'phone' => 'required|string|max:20',
            'id_number' => 'nullable|string|max:20',
        ]);

        $tenant->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile_number' => $validated['phone'],
            'national_id' => $validated['id_number'],
        ]);

        // Log activity
        TenantActivity::create([
            'landlord_id' => $landlordId,
            'tenant_id' => $tenant->id,
            'performed_by' => $user->id,
            'type' => 'profile_updated',
            'description' => 'Tenant profile was updated.',
            'metadata' => ['changes' => array_keys($validated)],
        ]);

        return Redirect::back()->with('success', 'Tenant updated successfully.');
    }

    // ==================== PAYMENT RECORDING API ====================

    /**
     * Search tenants for payment recording (JSON API).
     */
    public function search(Request $request)
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            return response()->json(['data' => []], 403);
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json(['data' => []]);
        }

        $tenants = User::where('role', 'tenant')
            ->where('landlord_id', $landlordId)
            ->whereHas('leases', fn ($q) => $q->where('is_active', true))
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('mobile_number', 'like', "%{$query}%")
                    ->orWhereHas('leases.unit', function ($q) use ($query) {
                        $q->where('unit_number', 'like', "%{$query}%");
                    });
            })
            ->with([
                'leases' => fn ($q) => $q->where('is_active', true)->with('unit.building:id,name'),
            ])
            ->limit(10)
            ->get()
            ->map(function ($tenant) {
                $activeLease = $tenant->leases->first();

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'email' => $tenant->email,
                    'mobile_number' => $tenant->mobile_number,
                    'unit' => $activeLease?->unit ? [
                        'id' => $activeLease->unit->id,
                        'unit_number' => $activeLease->unit->unit_number,
                        'building_name' => $activeLease->unit->building?->name,
                    ] : null,
                    'lease_id' => $activeLease?->id,
                ];
            });

        return response()->json(['data' => $tenants]);
    }

    /**
     * Get outstanding invoices for a tenant (JSON API).
     */
    public function outstandingInvoices(User $tenant)
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            return response()->json(['data' => []], 403);
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($tenant->landlord_id !== $landlordId) {
            return response()->json(['data' => []], 403);
        }

        $activeLease = $tenant->leases()->where('is_active', true)->first();

        if (! $activeLease) {
            return response()->json(['data' => []]);
        }

        $invoices = Invoice::where('lease_id', $activeLease->id)
            ->whereIn('status', ['draft', 'sent', 'partial', 'overdue'])
            ->whereRaw('total_due > amount_paid')
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function ($invoice) {
                $balance = $invoice->total_due - $invoice->amount_paid;

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'description' => $invoice->description ?? "Invoice #{$invoice->invoice_number}",
                    'total_due' => $invoice->total_due,
                    'amount_paid' => $invoice->amount_paid,
                    'balance' => $balance,
                    'due_date' => $invoice->due_date?->format('Y-m-d'),
                    'status' => $invoice->status,
                ];
            });

        $totalOutstanding = $invoices->sum('balance');

        return response()->json([
            'data' => $invoices,
            'total_outstanding' => $totalOutstanding,
        ]);
    }

    public function refundablePayments(User $tenant)
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            return response()->json(['data' => []], 403);
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($tenant->landlord_id !== $landlordId) {
            return response()->json(['data' => []], 403);
        }

        $payments = Payment::where('landlord_id', $landlordId)
            ->whereHas('lease', function ($query) use ($tenant) {
                $query->where('tenant_id', $tenant->id);
            })
            ->whereNotIn('status', ['voided', 'refunded'])
            ->with(['lease.unit.building', 'invoice', 'refunds'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($payment) {
                $totalRefunded = $payment->refunds->sum('amount');
                $refundableAmount = max(0, $payment->amount - $totalRefunded);

                if ($refundableAmount <= 0) {
                    return null;
                }

                return [
                    'id' => $payment->id,
                    'reference' => $payment->reference ?? "PAY-{$payment->id}",
                    'amount' => $payment->amount,
                    'refunded_amount' => $totalRefunded,
                    'refundable_amount' => $refundableAmount,
                    'payment_method' => $payment->payment_method,
                    'payment_date' => $payment->created_at->format('Y-m-d'),
                    'invoice_number' => $payment->invoice?->invoice_number,
                    'unit' => $payment->lease?->unit?->unit_number,
                    'building' => $payment->lease?->unit?->building?->name,
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'data' => $payments,
        ]);
    }

    // ==================== NOTES ====================

    /**
     * Add a note to a tenant.
     */
    public function addNote(Request $request, User $tenant)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($tenant->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'is_pinned' => 'boolean',
        ]);

        $note = TenantNote::create([
            'landlord_id' => $landlordId,
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'content' => $validated['content'],
            'is_pinned' => $validated['is_pinned'] ?? false,
        ]);

        TenantActivity::create([
            'landlord_id' => $landlordId,
            'tenant_id' => $tenant->id,
            'performed_by' => $user->id,
            'type' => 'note_added',
            'description' => 'A note was added to the tenant profile.',
        ]);

        return Redirect::back()->with('success', 'Note added.');
    }

    /**
     * Update a note.
     */
    public function updateNote(Request $request, TenantNote $note)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($note->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'is_pinned' => 'boolean',
        ]);

        $note->update($validated);

        return Redirect::back()->with('success', 'Note updated.');
    }

    /**
     * Delete a note.
     */
    public function deleteNote(TenantNote $note)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($note->landlord_id !== $landlordId) {
            abort(403);
        }

        $note->delete();

        return Redirect::back()->with('success', 'Note deleted.');
    }

    // ==================== EMERGENCY CONTACTS ====================

    /**
     * Add an emergency contact.
     */
    public function addEmergencyContact(Request $request, User $tenant)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($tenant->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'relationship' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_primary' => 'boolean',
        ]);

        // If setting as primary, unset others
        if ($validated['is_primary'] ?? false) {
            EmergencyContact::where('tenant_id', $tenant->id)->update(['is_primary' => false]);
        }

        EmergencyContact::create([
            'landlord_id' => $landlordId,
            'tenant_id' => $tenant->id,
            ...$validated,
        ]);

        TenantActivity::create([
            'landlord_id' => $landlordId,
            'tenant_id' => $tenant->id,
            'performed_by' => $user->id,
            'type' => 'emergency_contact_added',
            'description' => "Emergency contact '{$validated['name']}' was added.",
        ]);

        return Redirect::back()->with('success', 'Emergency contact added.');
    }

    /**
     * Update an emergency contact.
     */
    public function updateEmergencyContact(Request $request, EmergencyContact $contact)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($contact->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'relationship' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_primary' => 'boolean',
        ]);

        // If setting as primary, unset others
        if ($validated['is_primary'] ?? false) {
            EmergencyContact::where('tenant_id', $contact->tenant_id)
                ->where('id', '!=', $contact->id)
                ->update(['is_primary' => false]);
        }

        $contact->update($validated);

        return Redirect::back()->with('success', 'Emergency contact updated.');
    }

    /**
     * Delete an emergency contact.
     */
    public function deleteEmergencyContact(EmergencyContact $contact)
    {
        $user = auth()->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($contact->landlord_id !== $landlordId) {
            abort(403);
        }

        $contact->delete();

        return Redirect::back()->with('success', 'Emergency contact deleted.');
    }

    // ==================== TENANT LEDGER ====================

    /**
     * Display tenant ledger/statement with all financial transactions.
     */
    public function ledger(Request $request, User $tenant)
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($tenant->landlord_id !== $landlordId) {
            abort(403, 'Access denied.');
        }

        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $transactions = $this->buildLedgerTransactions($tenant, $dateFrom, $dateTo);

        $activeLease = $tenant->leases()->where('is_active', true)->with('unit.building')->first();

        $summary = [
            'total_invoiced' => $transactions->where('type', 'invoice')->sum('amount'),
            'total_paid' => $transactions->where('type', 'payment')->sum('amount'),
            'total_refunds' => $transactions->where('type', 'refund')->sum('amount'),
            'total_credits' => $transactions->where('type', 'credit_note')->sum('amount'),
            'current_balance' => $transactions->last()['running_balance'] ?? 0,
            'deposit_held' => $activeLease?->deposit_amount ?? 0,
            'wallet_balance' => $activeLease?->wallet_balance ?? 0,
        ];

        return Inertia::render('Tenants/Ledger', [
            'tenant' => $tenant->only(['id', 'name', 'email', 'mobile_number']),
            'activeLease' => $activeLease,
            'transactions' => $transactions->values(),
            'summary' => $summary,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Export tenant ledger as PDF.
     */
    public function ledgerPdf(Request $request, User $tenant)
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($tenant->landlord_id !== $landlordId) {
            abort(403, 'Access denied.');
        }

        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $transactions = $this->buildLedgerTransactions($tenant, $dateFrom, $dateTo);
        $activeLease = $tenant->leases()->where('is_active', true)->with('unit.building')->first();

        $summary = [
            'total_invoiced' => $transactions->where('type', 'invoice')->sum('amount'),
            'total_paid' => $transactions->where('type', 'payment')->sum('amount'),
            'total_refunds' => $transactions->where('type', 'refund')->sum('amount'),
            'current_balance' => $transactions->last()['running_balance'] ?? 0,
        ];

        $landlord = User::find($landlordId);
        $invoiceSetting = $landlord->getOrCreateInvoiceSetting();

        $pdf = Pdf::loadView('tenants.ledger-pdf', [
            'tenant' => $tenant,
            'activeLease' => $activeLease,
            'transactions' => $transactions,
            'summary' => $summary,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'landlord' => $landlord,
            'invoiceSetting' => $invoiceSetting,
            'generatedAt' => now(),
        ]);

        $filename = "statement-{$tenant->name}-".now()->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Email tenant ledger/statement.
     */
    public function ledgerEmail(Request $request, User $tenant)
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($tenant->landlord_id !== $landlordId) {
            abort(403, 'Access denied.');
        }

        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $transactions = $this->buildLedgerTransactions($tenant, $dateFrom, $dateTo);
        $activeLease = $tenant->leases()->where('is_active', true)->with('unit.building')->first();

        $summary = [
            'total_invoiced' => $transactions->where('type', 'invoice')->sum('amount'),
            'total_paid' => $transactions->where('type', 'payment')->sum('amount'),
            'total_refunds' => $transactions->where('type', 'refund')->sum('amount'),
            'current_balance' => $transactions->last()['running_balance'] ?? 0,
        ];

        $landlord = User::find($landlordId);
        $invoiceSetting = $landlord->getOrCreateInvoiceSetting();

        $pdf = Pdf::loadView('tenants.ledger-pdf', [
            'tenant' => $tenant,
            'activeLease' => $activeLease,
            'transactions' => $transactions,
            'summary' => $summary,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'landlord' => $landlord,
            'invoiceSetting' => $invoiceSetting,
            'generatedAt' => now(),
        ]);

        $filename = "statement-{$tenant->name}-".now()->format('Y-m-d').'.pdf';
        $pdfContent = $pdf->output();

        Mail::send('emails.tenant-statement', [
            'tenant' => $tenant,
            'landlord' => $landlord,
            'summary' => $summary,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ], function ($message) use ($tenant, $pdfContent, $filename, $landlord) {
            $message->to($tenant->email, $tenant->name)
                ->subject('Your Account Statement')
                ->attachData($pdfContent, $filename, ['mime' => 'application/pdf']);

            if ($landlord->email) {
                $message->from($landlord->email, $landlord->name ?? 'Property Management');
            }
        });

        return Redirect::back()->with('success', 'Statement emailed to tenant successfully.');
    }

    /**
     * Build ledger transactions for a tenant.
     */
    private function buildLedgerTransactions(User $tenant, ?string $dateFrom, ?string $dateTo)
    {
        $leaseIds = $tenant->leases()->pluck('id');

        $invoices = Invoice::whereIn('lease_id', $leaseIds)
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'type' => 'invoice',
                'date' => $inv->created_at,
                'description' => "Invoice #{$inv->invoice_number}",
                'reference' => $inv->invoice_number,
                'amount' => $inv->total_due ?? $inv->total_amount ?? 0,
                'status' => $inv->status,
            ]);

        $payments = Payment::whereIn('lease_id', $leaseIds)
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->with('invoice:id,invoice_number')
            ->get()
            ->map(fn ($pmt) => [
                'id' => $pmt->id,
                'type' => 'payment',
                'date' => $pmt->created_at,
                'description' => 'Payment'.($pmt->invoice ? " for Invoice #{$pmt->invoice->invoice_number}" : ''),
                'reference' => $pmt->reference ?? "PAY-{$pmt->id}",
                'amount' => $pmt->amount,
                'status' => $pmt->status ?? 'completed',
            ]);

        $refunds = Refund::whereHas('payment', fn ($q) => $q->whereIn('lease_id', $leaseIds))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->where('status', 'completed')
            ->get()
            ->map(fn ($ref) => [
                'id' => $ref->id,
                'type' => 'refund',
                'date' => $ref->processed_at ?? $ref->created_at,
                'description' => "Refund - {$ref->reason}",
                'reference' => "REF-{$ref->id}",
                'amount' => $ref->amount,
                'status' => $ref->status,
            ]);

        $creditNotes = CreditNote::whereIn('lease_id', $leaseIds)
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->whereIn('status', ['approved', 'applied'])
            ->get()
            ->map(fn ($cn) => [
                'id' => $cn->id,
                'type' => 'credit_note',
                'date' => $cn->approved_at ?? $cn->created_at,
                'description' => "Credit Note - {$cn->reason_label}",
                'reference' => $cn->credit_number,
                'amount' => $cn->applied_amount ?: $cn->amount,
                'status' => $cn->status,
            ]);

        $transactions = $invoices->concat($payments)->concat($refunds)->concat($creditNotes)
            ->sortBy('date')
            ->values();

        $runningBalance = 0;
        $transactions = $transactions->map(function ($txn) use (&$runningBalance) {
            if ($txn['type'] === 'invoice') {
                $runningBalance += $txn['amount'];
                $txn['debit'] = $txn['amount'];
                $txn['credit'] = 0;
            } elseif ($txn['type'] === 'payment') {
                $runningBalance -= $txn['amount'];
                $txn['debit'] = 0;
                $txn['credit'] = $txn['amount'];
            } elseif ($txn['type'] === 'refund') {
                $runningBalance += $txn['amount'];
                $txn['debit'] = $txn['amount'];
                $txn['credit'] = 0;
            } elseif ($txn['type'] === 'credit_note') {
                $runningBalance -= $txn['amount'];
                $txn['debit'] = 0;
                $txn['credit'] = $txn['amount'];
            }

            $txn['running_balance'] = $runningBalance;

            return $txn;
        });

        return $transactions;
    }

    // ==================== TENANT HISTORY ====================

    /**
     * Display tenant history (past tenants).
     */
    public function history(Request $request)
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $query = User::where('role', 'tenant')
            ->where('landlord_id', $landlordId)
            ->whereDoesntHave('leases', fn ($q) => $q->where('is_active', true))
            ->whereHas('leases');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile_number', 'like', "%{$search}%");
            });
        }

        // Building filter
        if ($request->filled('building_id')) {
            $query->whereHas('leases.unit', function ($q) use ($request) {
                $q->where('building_id', $request->building_id);
            });
        }

        // Load relationships
        $query->with([
            'leases' => fn ($q) => $q->with([
                'unit.building:id,name',
                'moveOut:id,lease_id,move_out_date,reason,status',
            ])->orderBy('end_date', 'desc'),
        ]);

        // Sorting
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $pastTenants = $query->paginate(20)->withQueryString();

        // Transform data for display
        $pastTenants->getCollection()->transform(function ($tenant) {
            $lastLease = $tenant->leases->first();

            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'email' => $tenant->email,
                'mobile_number' => $tenant->mobile_number,
                'last_lease' => $lastLease ? [
                    'id' => $lastLease->id,
                    'unit_number' => $lastLease->unit->unit_number ?? 'N/A',
                    'building_name' => $lastLease->unit->building->name ?? 'N/A',
                    'start_date' => $lastLease->start_date,
                    'end_date' => $lastLease->end_date,
                    'duration_months' => $lastLease->start_date && $lastLease->end_date
                        ? $lastLease->start_date->diffInMonths($lastLease->end_date)
                        : null,
                    'move_out' => $lastLease->moveOut ? [
                        'date' => $lastLease->moveOut->move_out_date,
                        'reason' => $lastLease->moveOut->reason,
                        'status' => $lastLease->moveOut->status,
                    ] : null,
                ] : null,
                'total_leases' => $tenant->leases->count(),
            ];
        });

        // Get stats
        $stats = [
            'total_past_tenants' => User::where('role', 'tenant')
                ->where('landlord_id', $landlordId)
                ->whereDoesntHave('leases', fn ($q) => $q->where('is_active', true))
                ->whereHas('leases')
                ->count(),
        ];

        // Get buildings for filter dropdown
        $buildings = \App\Models\Building::where('landlord_id', $landlordId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Tenants/History', [
            'pastTenants' => $pastTenants,
            'stats' => $stats,
            'buildings' => $buildings,
            'filters' => $request->only(['search', 'building_id', 'sort', 'direction']),
        ]);
    }

    // ==================== TENANT-FACING METHODS ====================

    /**
     * Display the tenant's payment history.
     */
    public function payments(Request $request)
    {
        $user = auth()->user();

        if (! $user->isTenant()) {
            abort(403, 'This page is only for tenants.');
        }

        $lease = $user->lease;

        if (! $lease) {
            return Inertia::render('Tenant/Payments', [
                'hasLease' => false,
                'payments' => [],
                'invoices' => [],
            ]);
        }

        $payments = Payment::where('lease_id', $lease->id)
            ->with('invoice')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $invoices = Invoice::where('lease_id', $lease->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $totalPaid = Payment::where('lease_id', $lease->id)->sum('amount');
        $totalInvoiced = Invoice::where('lease_id', $lease->id)->sum('total_due');
        $balance = $totalPaid - $totalInvoiced;

        return Inertia::render('Tenant/Payments', [
            'hasLease' => true,
            'payments' => $payments,
            'invoices' => $invoices,
            'totalPaid' => $totalPaid,
            'totalInvoiced' => $totalInvoiced,
            'balance' => $balance,
        ]);
    }

    /**
     * Display the tenant's lease details.
     */
    public function lease()
    {
        $user = auth()->user();

        if (! $user->isTenant()) {
            abort(403, 'This page is only for tenants.');
        }

        $lease = $user->lease()
            ->with(['unit.building', 'rentHistory', 'documents'])
            ->first();

        if (! $lease) {
            return Inertia::render('Tenant/Lease', [
                'hasLease' => false,
                'lease' => null,
            ]);
        }

        return Inertia::render('Tenant/Lease', [
            'hasLease' => true,
            'lease' => $lease,
            'unit' => $lease->unit,
            'building' => $lease->unit->building,
            'rentHistory' => $lease->rentHistory()->orderBy('effective_date', 'desc')->get(),
        ]);
    }
}
