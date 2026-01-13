<?php

namespace App\Http\Controllers;

use App\Services\SecurityLogger;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorService $twoFactorService,
        protected SecurityLogger $securityLogger
    ) {}

    /**
     * Show the 2FA settings page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Settings/TwoFactor', [
            'enabled' => $this->twoFactorService->isEnabled($user),
            'required' => $this->twoFactorService->isRequired($user),
            'recoveryCodesCount' => $this->twoFactorService->getRecoveryCodes($user)->count(),
        ]);
    }

    /**
     * Enable 2FA - Step 1: Generate secret and show QR code.
     */
    public function enable(Request $request): Response
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Generate new secret
        $secret = $this->twoFactorService->generateSecretKey();

        // Store temporarily in session
        session(['two_factor_secret' => $secret]);

        // Generate QR code
        $qrCode = $this->twoFactorService->getQrCodeSvg($user, $secret);

        return Inertia::render('Settings/TwoFactorSetup', [
            'qrCode' => $qrCode,
            'secret' => $secret,
        ]);
    }

    /**
     * Confirm 2FA - Step 2: Verify code and enable.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();
        $secret = session('two_factor_secret');

        if (! $secret) {
            return back()->withErrors([
                'code' => 'Two-factor setup session expired. Please start again.',
            ]);
        }

        // Verify the code
        if (! $this->twoFactorService->verifySecret($secret, $request->code)) {
            return back()->withErrors([
                'code' => 'The provided two-factor code is invalid.',
            ]);
        }

        // Enable 2FA
        $this->twoFactorService->enable($user, $secret);

        // Clear session
        session()->forget('two_factor_secret');

        // Log the event
        $this->securityLogger->logTwoFactorEnabled($user);

        return redirect()->route('two-factor.index')
            ->with('status', 'Two-factor authentication has been enabled.');
    }

    /**
     * Show recovery codes.
     */
    public function showRecoveryCodes(Request $request): Response
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if (! $this->twoFactorService->isEnabled($user)) {
            abort(403, 'Two-factor authentication is not enabled.');
        }

        return Inertia::render('Settings/TwoFactorRecoveryCodes', [
            'recoveryCodes' => $this->twoFactorService->getRecoveryCodes($user)->toArray(),
        ]);
    }

    /**
     * Regenerate recovery codes.
     */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if (! $this->twoFactorService->isEnabled($user)) {
            abort(403, 'Two-factor authentication is not enabled.');
        }

        $this->twoFactorService->regenerateRecoveryCodes($user);

        return redirect()->route('two-factor.recovery-codes')
            ->with('status', 'Recovery codes have been regenerated.');
    }

    /**
     * Disable 2FA.
     */
    public function disable(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        // Verify the code before disabling
        $isValidCode = $this->twoFactorService->verify($user, $request->code);
        $isValidRecoveryCode = ! $isValidCode && $this->twoFactorService->verifyRecoveryCode($user, $request->code);

        if (! $isValidCode && ! $isValidRecoveryCode) {
            return back()->withErrors([
                'code' => 'The provided code is invalid.',
            ]);
        }

        // Check if 2FA is required for this user
        if ($this->twoFactorService->isRequired($user)) {
            return back()->withErrors([
                'code' => 'Two-factor authentication cannot be disabled for your account.',
            ]);
        }

        $this->twoFactorService->disable($user);

        // Log the event
        $this->securityLogger->logTwoFactorDisabled($user);

        return redirect()->route('two-factor.index')
            ->with('status', 'Two-factor authentication has been disabled.');
    }

    /**
     * Show the 2FA challenge page (after login).
     */
    public function challenge(): Response
    {
        if (! session('login.id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    /**
     * Verify the 2FA challenge.
     */
    public function verifyChallenge(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $userId = session('login.id');
        $remember = session('login.remember', false);

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = \App\Models\User::findOrFail($userId);

        // Try TOTP code first
        if ($request->filled('code')) {
            if (! $this->twoFactorService->verify($user, $request->code)) {
                $this->securityLogger->logTwoFactorFailed($user);

                throw ValidationException::withMessages([
                    'code' => 'The provided two-factor code is invalid.',
                ]);
            }
        }
        // Try recovery code
        elseif ($request->filled('recovery_code')) {
            if (! $this->twoFactorService->verifyRecoveryCode($user, $request->recovery_code)) {
                $this->securityLogger->logTwoFactorFailed($user);

                throw ValidationException::withMessages([
                    'recovery_code' => 'The provided recovery code is invalid.',
                ]);
            }
        } else {
            throw ValidationException::withMessages([
                'code' => 'Please provide a two-factor code or recovery code.',
            ]);
        }

        // Clear login session data
        session()->forget(['login.id', 'login.remember']);

        // Log in the user
        auth()->login($user, $remember);

        $request->session()->regenerate();

        // Log successful login
        $this->securityLogger->logLogin($user);

        return redirect()->intended(route('dashboard'));
    }
}
