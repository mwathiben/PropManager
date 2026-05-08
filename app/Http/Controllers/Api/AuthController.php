<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
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
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => ['required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'device_name' => 'required|string|max:255',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $user->role = 'tenant';
        $user->save();

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
        ], 201);
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
            'super_admin' => ['tenant:read', 'landlord:manage', 'integration:webhook', 'admin:all'],
            default => ['tenant:read'],
        };
    }
}
