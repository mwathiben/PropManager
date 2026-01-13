<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\SecurityLogger;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        protected SecurityLogger $securityLogger,
        protected TwoFactorService $twoFactorService
    ) {}

    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();

        // Check if user has 2FA enabled
        if ($this->twoFactorService->isEnabled($user)) {
            // Log the user out and store their ID for 2FA verification
            Auth::logout();

            $request->session()->put([
                'login.id' => $user->id,
                'login.remember' => $request->boolean('remember'),
            ]);

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        // Mark 2FA as verified (not required for this user)
        session(['two_factor_verified' => true]);

        // Log successful login
        $this->securityLogger->logLogin($user);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();

        Auth::guard('web')->logout();

        // Log logout before session is invalidated
        if ($user) {
            $this->securityLogger->logLogout($user);
        }

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
