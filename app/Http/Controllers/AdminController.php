<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Services\IncidentDetector;
use App\Services\SecurityLogger;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AdminController extends Controller implements HasMiddleware
{
    /**
     * Phase-18 AUTHZ-3: route through Gate::authorize('access-admin')
     * instead of inline isSuperAdmin() checks scattered across each
     * action. Two effects:
     *   - Phase-13 DPA-4 Gate::before restriction now applies (a
     *     DPA-restricted super-admin was previously NOT actually
     *     restricted because the inline check bypassed the Gate
     *     layer)
     *   - Future authorization concerns (2FA, IP allowlist, etc.)
     *     attach to one Gate definition rather than 10+ inline
     *     checks
     * The 'impersonate' / 'admin.disable-impersonate' paths retain
     * their own per-action Gate::allows('impersonate', $target)
     * because they pass a target.
     *
     * Laravel 11 removed the controller-side $this->middleware() method;
     * HasMiddleware + static middleware() is the supported replacement.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                Gate::authorize('access-admin');

                return $next($request);
            }, except: ['stopImpersonating']),
        ];
    }

    public function __construct(private readonly SecurityLogger $securityLogger) {}

    /**
     * Display the list of all landlords.
     */
    public function landlords(Request $request)
    {
        $query = User::where('role', 'landlord');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $landlords = $query
            ->withCount('properties')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(function ($landlord) {
                // Count units across all properties
                $landlord->units_count = Unit::withoutGlobalScope('landlord')
                    ->where('landlord_id', $landlord->id)
                    ->count();
                $landlord->occupied_units = Unit::withoutGlobalScope('landlord')
                    ->where('landlord_id', $landlord->id)
                    ->where('status', 'occupied')
                    ->count();
                $landlord->total_revenue = Payment::withoutGlobalScope('landlord')
                    ->where('landlord_id', $landlord->id)
                    ->sum('amount');

                return $landlord;
            });

        return Inertia::render('Admin/Landlords', [
            'landlords' => $landlords,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Display the list of all users with filters.
     */
    public function users(Request $request)
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        $users = $query
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'filters' => $request->only(['search', 'role']),
            'roles' => [
                'super_admin' => 'Super Admin',
                'landlord' => 'Landlord',
                'caretaker' => 'Caretaker',
                'tenant' => 'Tenant',
                'water_client' => 'Water Client',
            ],
        ]);
    }

    /**
     * View details of a specific landlord.
     */
    public function showLandlord(User $user)
    {
        if ($user->role !== 'landlord') {
            abort(404);
        }

        $properties = Property::withoutGlobalScope('landlord')
            ->where('landlord_id', $user->id)
            ->with('buildings.units')
            ->get();

        $stats = [
            'properties_count' => $properties->count(),
            'buildings_count' => $properties->sum(fn ($p) => $p->buildings->count()),
            'units_count' => Unit::withoutGlobalScope('landlord')
                ->where('landlord_id', $user->id)
                ->count(),
            'occupied_units' => Unit::withoutGlobalScope('landlord')
                ->where('landlord_id', $user->id)
                ->where('status', 'occupied')
                ->count(),
            'total_revenue' => Payment::withoutGlobalScope('landlord')
                ->where('landlord_id', $user->id)
                ->sum('amount'),
            'total_invoiced' => Invoice::withoutGlobalScope('landlord')
                ->where('landlord_id', $user->id)
                ->sum('total_amount'),
        ];

        $caretakers = User::where('landlord_id', $user->id)
            ->where('role', 'caretaker')
            ->get();

        return Inertia::render('Admin/LandlordShow', [
            'landlord' => $user,
            'properties' => $properties,
            'stats' => $stats,
            'caretakers' => $caretakers,
        ]);
    }

    /**
     * Impersonate a user (login as them).
     */
    public function impersonate(User $user)
    {
        // AUDIT-1: only super admins can impersonate (defence-in-depth on top
        // of the route middleware) — and the action is logged BEFORE the
        // session swap so the actor on the SecurityLog is the admin, not the
        // impersonated user.
        $admin = Auth::user();
        if (! $admin || ! $admin->isSuperAdmin()) {
            abort(403);
        }

        if ($user->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Cannot impersonate super admin users.');
        }

        $this->securityLogger->logImpersonationStart($admin, $user);

        // Phase-13 BREACH-5: each impersonation passes throttle:sensitive
        // individually, but a burst against many tenants can still be
        // pathological. checkImpersonationFrequency consults
        // security_logs and escalates if the admin has crossed the
        // hourly threshold (default 5/hour).
        try {
            app(IncidentDetector::class)->checkImpersonationFrequency($admin->id);
        } catch (\Throwable $e) {
            Log::warning('IncidentDetector failed during impersonation-frequency check', [
                'error' => $e->getMessage(),
            ]);
        }

        session(['impersonating' => $admin->id]);
        session(['impersonating_name' => $admin->name]);

        auth()->login($user);

        // CRYPTO-5: rotate the session id across the privilege transition
        // so a leaked / fixated cookie can't ride from admin → user.
        request()->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', "Now viewing as {$user->name}. Click 'Stop Impersonating' in the header to return.");
    }

    /**
     * Stop impersonating and return to admin account.
     */
    public function stopImpersonating()
    {
        $originalId = session('impersonating');

        if (! $originalId) {
            return redirect()->route('dashboard')
                ->with('error', 'You are not impersonating anyone.');
        }

        $originalUser = User::find($originalId);

        if (! $originalUser || ! $originalUser->isSuperAdmin()) {
            session()->forget(['impersonating', 'impersonating_name']);

            return redirect()->route('login')
                ->with('error', 'Original user not found. Please login again.');
        }

        // AUDIT-1: log the end-of-impersonation while we still know the
        // currently-impersonated user.
        $impersonated = Auth::user();
        if ($impersonated) {
            $this->securityLogger->logImpersonationEnd($originalUser, $impersonated);
        }

        session()->forget(['impersonating', 'impersonating_name']);
        auth()->login($originalUser);

        // CRYPTO-5: rotate the session id back to admin context so a cookie
        // sniffed during impersonation can't hijack the post-stop session.
        request()->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', 'Returned to your admin account.');
    }

    /**
     * Admin settings page.
     *
     * Note: Email and SMS configuration has been consolidated to the Notification Center
     * (Operations > Notifications > Settings). This page now only handles payment gateway settings.
     */
    public function settings()
    {
        $paymentSettings = Setting::getSystemByCategory('payment');

        return Inertia::render('Admin/Settings', [
            'paymentSettings' => [
                'paystack_public_key' => $this->maskApiKey($paymentSettings['paystack_public_key'] ?? ''),
                'paystack_secret_key' => $this->maskApiKey($paymentSettings['paystack_secret_key'] ?? ''),
                'has_paystack_public_key' => ! empty($paymentSettings['paystack_public_key']),
                'has_paystack_secret_key' => ! empty($paymentSettings['paystack_secret_key']),
            ],
        ]);
    }

    /**
     * Mask an API key for display (show only last 4 characters).
     */
    protected function maskApiKey(?string $key): string
    {
        if (empty($key) || strlen($key) < 8) {
            return '';
        }

        return str_repeat('•', strlen($key) - 4).substr($key, -4);
    }

    /**
     * Update Paystack payment gateway settings.
     */
    public function updatePaymentSettings(Request $request)
    {
        $validated = $request->validate([
            'paystack_public_key' => 'nullable|string|max:255',
            'paystack_secret_key' => 'nullable|string|max:255',
        ]);

        // Only update if value is provided (not empty)
        $changedFields = [];

        if (! empty($validated['paystack_public_key'])) {
            Setting::setSystem(
                'paystack_public_key',
                $validated['paystack_public_key'],
                true,
                'payment',
                'Paystack public key for payment processing'
            );
            $changedFields[] = 'paystack_public_key';
        }

        if (! empty($validated['paystack_secret_key'])) {
            Setting::setSystem(
                'paystack_secret_key',
                $validated['paystack_secret_key'],
                true,
                'payment',
                'Paystack secret key for payment processing'
            );
            $changedFields[] = 'paystack_secret_key';
        }

        // OBS-6: super-admin (system-wide) Paystack key edits go in the
        // same audit trail as per-landlord changes. Field names only —
        // never the secret values themselves.
        if ($changedFields !== [] && ($actor = auth()->user())) {
            $this->securityLogger->logPaymentConfigChange(
                $actor,
                $changedFields,
                ['scope' => 'system']
            );
        }

        return back()->with('success', 'Payment gateway settings updated successfully.');
    }

    /**
     * Test Paystack connection with provided credentials.
     */
    public function testPaystackConnection(Request $request)
    {
        $validated = $request->validate([
            'paystack_secret_key' => 'required|string',
        ]);

        try {
            // HANDLE-10: bound the connection test so a Paystack outage
            // doesn't hang the admin UI on the synchronous request path.
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$validated['paystack_secret_key'],
            ])->connectTimeout(3)->timeout(8)->get('https://api.paystack.co/balance');

            if ($response->successful() && ($response->json('status') ?? false)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Paystack connection successful!',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid Paystack credentials. Please check your API keys.',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to connect to Paystack: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new landlord account.
     */
    public function createLandlord(Request $request)
    {
        // VALID-9: enforce the same password policy as user-self-registration.
        // Without this, super-admin-created landlord accounts could ship with
        // 8-char passwords that fail HIBP / strength rules.
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'string', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
            'mobile_number' => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'mobile_number' => $validated['mobile_number'] ?? null,
        ]);
        $user->role = 'landlord';
        $user->save();

        // AUDIT-10 follow-up: log the role grant when an admin provisions a
        // landlord account from the admin UI.
        $this->securityLogger->logRoleChange($user, 'none', $user->role, Auth::user());

        return redirect()->back()
            ->with('success', "Landlord account created for {$user->name}.");
    }

    /**
     * Update user status (activate/deactivate).
     */
    public function toggleUserStatus(User $user)
    {
        // Don't allow deactivating super admins
        if ($user->isSuperAdmin()) {
            return redirect()->back()
                ->with('error', 'Cannot deactivate super admin users.');
        }

        // PRIV-14: refuse to deactivate a landlord that still has active
        // leases — pre-fix, an admin could accidentally lock out a real
        // landlord's tenants from rent payments by clicking the toggle.
        $isDeactivating = (bool) $user->email_verified_at;
        if ($isDeactivating && $user->isLandlord()) {
            $hasActiveLeases = \App\Models\Lease::where('landlord_id', $user->id)
                ->where('is_active', true)
                ->exists();
            if ($hasActiveLeases) {
                return redirect()->back()
                    ->with('error', 'Cannot deactivate a landlord with active leases. Terminate or transfer leases first.');
            }
        }

        // Toggle email_verified_at as a simple activation status
        // In production, you might want a dedicated 'is_active' column
        if ($isDeactivating) {
            $user->email_verified_at = null;
            $message = "User {$user->name} has been deactivated.";
            $event = 'admin_user_deactivated';
        } else {
            $user->email_verified_at = now();
            $message = "User {$user->name} has been activated.";
            $event = 'admin_user_activated';
        }

        $user->save();

        // PRIV-14: audit trail for activation/deactivation since the
        // toggle gates downstream login (User::canLogin checks
        // email_verified_at) and tenant access.
        $this->securityLogger->log(
            $event,
            "Admin toggled {$user->email}",
            ['target_user_id' => $user->id, 'target_email' => $user->email, 'role' => $user->role],
            \App\Models\SecurityLog::SEVERITY_WARNING,
            Auth::user()
        );

        return redirect()->back()->with('success', $message);
    }
}
