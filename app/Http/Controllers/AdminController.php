<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class AdminController extends Controller
{
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
        // Don't allow impersonating super admins
        if ($user->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Cannot impersonate super admin users.');
        }

        // Store the original admin ID in session
        session(['impersonating' => auth()->id()]);
        session(['impersonating_name' => auth()->user()->name]);

        // Login as the target user
        auth()->login($user);

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

        session()->forget(['impersonating', 'impersonating_name']);
        auth()->login($originalUser);

        return redirect()->route('dashboard')
            ->with('success', 'Returned to your admin account.');
    }

    /**
     * Admin settings page.
     */
    public function settings()
    {
        // Get all system settings, grouped by category
        $paymentSettings = Setting::getSystemByCategory('payment');
        $emailSettings = Setting::getSystemByCategory('email');
        $smsSettings = Setting::getSystemByCategory('sms');

        return Inertia::render('Admin/Settings', [
            'paymentSettings' => [
                'paystack_public_key' => $this->maskApiKey($paymentSettings['paystack_public_key'] ?? ''),
                'paystack_secret_key' => $this->maskApiKey($paymentSettings['paystack_secret_key'] ?? ''),
                'has_paystack_public_key' => ! empty($paymentSettings['paystack_public_key']),
                'has_paystack_secret_key' => ! empty($paymentSettings['paystack_secret_key']),
            ],
            'emailSettings' => [
                'smtp_host' => $emailSettings['smtp_host'] ?? '',
                'smtp_port' => $emailSettings['smtp_port'] ?? '587',
                'smtp_username' => $emailSettings['smtp_username'] ?? '',
                'smtp_password' => $this->maskApiKey($emailSettings['smtp_password'] ?? ''),
                'smtp_encryption' => $emailSettings['smtp_encryption'] ?? 'tls',
                'mail_from_address' => $emailSettings['mail_from_address'] ?? '',
                'mail_from_name' => $emailSettings['mail_from_name'] ?? 'PropManager',
                'has_smtp_password' => ! empty($emailSettings['smtp_password']),
            ],
            'smsSettings' => [
                'africastalking_username' => $smsSettings['africastalking_username'] ?? '',
                'africastalking_api_key' => $this->maskApiKey($smsSettings['africastalking_api_key'] ?? ''),
                'africastalking_sender_id' => $smsSettings['africastalking_sender_id'] ?? '',
                'africastalking_environment' => $smsSettings['africastalking_environment'] ?? 'sandbox',
                'has_africastalking_api_key' => ! empty($smsSettings['africastalking_api_key']),
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
        if (! empty($validated['paystack_public_key'])) {
            Setting::setSystem(
                'paystack_public_key',
                $validated['paystack_public_key'],
                true,
                'payment',
                'Paystack public key for payment processing'
            );
        }

        if (! empty($validated['paystack_secret_key'])) {
            Setting::setSystem(
                'paystack_secret_key',
                $validated['paystack_secret_key'],
                true,
                'payment',
                'Paystack secret key for payment processing'
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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$validated['paystack_secret_key'],
            ])->get('https://api.paystack.co/balance');

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
     * Update email SMTP settings.
     */
    public function updateEmailSettings(Request $request)
    {
        $validated = $request->validate([
            'smtp_host' => 'required|string|max:255',
            'smtp_port' => 'required|integer|min:1|max:65535',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'smtp_encryption' => 'required|in:tls,ssl,none',
            'mail_from_address' => 'required|email|max:255',
            'mail_from_name' => 'required|string|max:255',
        ]);

        // Update non-sensitive settings
        Setting::setSystem('smtp_host', $validated['smtp_host'], false, 'email', 'SMTP server host');
        Setting::setSystem('smtp_port', (string) $validated['smtp_port'], false, 'email', 'SMTP server port');
        Setting::setSystem('smtp_username', $validated['smtp_username'] ?? '', false, 'email', 'SMTP username');
        Setting::setSystem('smtp_encryption', $validated['smtp_encryption'], false, 'email', 'SMTP encryption type');
        Setting::setSystem('mail_from_address', $validated['mail_from_address'], false, 'email', 'Default from email address');
        Setting::setSystem('mail_from_name', $validated['mail_from_name'], false, 'email', 'Default from name');

        // Only update password if provided
        if (! empty($validated['smtp_password'])) {
            Setting::setSystem('smtp_password', $validated['smtp_password'], true, 'email', 'SMTP password');
        }

        return back()->with('success', 'Email settings updated successfully.');
    }

    /**
     * Test email connection by sending a test email.
     */
    public function testEmailConnection(Request $request)
    {
        $validated = $request->validate([
            'smtp_host' => 'required|string',
            'smtp_port' => 'required|integer',
            'smtp_username' => 'nullable|string',
            'smtp_password' => 'nullable|string',
            'smtp_encryption' => 'required|in:tls,ssl,none',
            'mail_from_address' => 'required|email',
            'mail_from_name' => 'required|string',
            'test_email' => 'required|email',
        ]);

        try {
            // Temporarily configure the mailer
            config([
                'mail.mailers.smtp.host' => $validated['smtp_host'],
                'mail.mailers.smtp.port' => $validated['smtp_port'],
                'mail.mailers.smtp.username' => $validated['smtp_username'],
                'mail.mailers.smtp.password' => $validated['smtp_password'],
                'mail.mailers.smtp.encryption' => $validated['smtp_encryption'] === 'none' ? null : $validated['smtp_encryption'],
                'mail.from.address' => $validated['mail_from_address'],
                'mail.from.name' => $validated['mail_from_name'],
            ]);

            Mail::raw('This is a test email from PropManager to verify your SMTP settings are working correctly.', function ($message) use ($validated) {
                $message->to($validated['test_email'])
                    ->subject('PropManager - SMTP Test Email');
            });

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully to '.$validated['test_email'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update Africa's Talking SMS settings.
     */
    public function updateSmsSettings(Request $request)
    {
        $validated = $request->validate([
            'africastalking_username' => 'required|string|max:255',
            'africastalking_api_key' => 'nullable|string|max:255',
            'africastalking_sender_id' => 'nullable|string|max:20',
            'africastalking_environment' => 'required|in:sandbox,production',
        ]);

        Setting::setSystem('africastalking_username', $validated['africastalking_username'], false, 'sms', 'Africa\'s Talking username');
        Setting::setSystem('africastalking_sender_id', $validated['africastalking_sender_id'] ?? '', false, 'sms', 'SMS sender ID');
        Setting::setSystem('africastalking_environment', $validated['africastalking_environment'], false, 'sms', 'API environment (sandbox/production)');

        // Only update API key if provided
        if (! empty($validated['africastalking_api_key'])) {
            Setting::setSystem('africastalking_api_key', $validated['africastalking_api_key'], true, 'sms', 'Africa\'s Talking API key');
        }

        return back()->with('success', 'SMS settings updated successfully.');
    }

    /**
     * Test Africa's Talking SMS connection.
     */
    public function testSmsConnection(Request $request)
    {
        $validated = $request->validate([
            'africastalking_username' => 'required|string',
            'africastalking_api_key' => 'required|string',
            'africastalking_environment' => 'required|in:sandbox,production',
            'test_phone' => 'required|string',
        ]);

        try {
            $baseUrl = $validated['africastalking_environment'] === 'sandbox'
                ? 'https://api.sandbox.africastalking.com'
                : 'https://api.africastalking.com';

            $response = Http::withHeaders([
                'apiKey' => $validated['africastalking_api_key'],
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ])->asForm()->post($baseUrl.'/version1/messaging', [
                'username' => $validated['africastalking_username'],
                'to' => $validated['test_phone'],
                'message' => 'This is a test SMS from PropManager to verify your Africa\'s Talking settings.',
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['SMSMessageData']['Recipients'])) {
                $recipient = $data['SMSMessageData']['Recipients'][0] ?? null;
                if ($recipient && $recipient['status'] === 'Success') {
                    return response()->json([
                        'success' => true,
                        'message' => 'Test SMS sent successfully to '.$validated['test_phone'],
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'SMS sending failed: '.($data['SMSMessageData']['Message'] ?? 'Unknown error'),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test SMS: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new landlord account.
     */
    public function createLandlord(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'mobile_number' => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'mobile_number' => $validated['mobile_number'] ?? null,
            'role' => 'landlord',
        ]);

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

        // Toggle email_verified_at as a simple activation status
        // In production, you might want a dedicated 'is_active' column
        if ($user->email_verified_at) {
            $user->email_verified_at = null;
            $message = "User {$user->name} has been deactivated.";
        } else {
            $user->email_verified_at = now();
            $message = "User {$user->name} has been activated.";
        }

        $user->save();

        return redirect()->back()->with('success', $message);
    }
}
