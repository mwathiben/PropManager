<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->two_factor_secret) {
            return response()->json([
                'two_factor_required' => true,
                'email' => $user->email,
            ]);
        }

        $abilities = $this->getAbilitiesForUser($user);
        $token = $user->createToken($request->device_name, $abilities);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'token' => $token->plainTextToken,
            'abilities' => $abilities,
        ]);
    }

    public function twoFactorChallenge(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
            'device_name' => 'required|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! $user->two_factor_secret) {
            throw ValidationException::withMessages([
                'email' => ['Invalid two-factor authentication request.'],
            ]);
        }

        $twoFactorService = app(TwoFactorService::class);

        if (! $twoFactorService->verify($user, $request->code)) {
            throw ValidationException::withMessages([
                'code' => ['The provided two-factor code is invalid.'],
            ]);
        }

        $abilities = $this->getAbilitiesForUser($user);
        $token = $user->createToken($request->device_name, $abilities);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'token' => $token->plainTextToken,
            'abilities' => $abilities,
        ]);
    }

    public function register(Request $request)
    {
        // PRIV-9: tenant accounts are created by landlord/caretaker
        // invitation only. Pre-fix, anyone could POST here, mint a
        // tenant token, and reach landlord-scoped tenant endpoints
        // because TenantScope's writes-allowed-pre-auth gap keeps the
        // record visible until landlord_id is set. There is no business
        // case for unauthenticated public registration in this app, so
        // the endpoint is closed; see TenantInvitationController for
        // the supported flow.
        return response()->json([
            'message' => 'Registration is invitation-only. Contact your landlord for an invitation link.',
        ], 403);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'email_verified_at' => $user->email_verified_at,
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
        ]);
    }

    protected function getAbilitiesForUser(User $user): array
    {
        return match ($user->role) {
            'landlord' => ['tenant:read', 'landlord:manage'],
            'caretaker' => ['tenant:read', 'landlord:manage'],
            'tenant' => ['tenant:read'],
            // A water client must never inherit tenant:read (the default would leak it).
            'water_client' => ['water_client:read'],
            'super_admin' => ['tenant:read', 'landlord:manage', 'integration:webhook', 'admin:all'],
            default => ['tenant:read'],
        };
    }
}
