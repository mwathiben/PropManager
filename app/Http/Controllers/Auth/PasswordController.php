<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SecurityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function __construct(
        protected SecurityLogger $securityLogger
    ) {}

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Log password change
        $this->securityLogger->logPasswordChange($user);

        // Invalidate other sessions if configured
        if (config('security.session.invalidate_on_password_change', true)) {
            $this->invalidateOtherSessions($request);
        }

        return back()->with('status', 'password-updated');
    }

    /**
     * Invalidate all other sessions for the user.
     */
    protected function invalidateOtherSessions(Request $request): void
    {
        if (config('session.driver') === 'database') {
            DB::table('sessions')
                ->where('user_id', $request->user()->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        // Regenerate the current session
        $request->session()->regenerate();
    }
}
