<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SecurityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-25 API-AUTH-1 + AUTH-2 + AUTH-3: API key (Sanctum PAT)
 * self-serve for landlords + super-admins.
 *
 * Before Phase 25 the only Sanctum PAT creation path was
 * AuthController::login (mobile-app flow with device_name) — a
 * landlord who wanted to connect QuickBooks/Zapier had no way to
 * issue a token from the UI. This controller closes the gap:
 *
 *   - GET  /settings/api-tokens — list active tokens (name, scopes,
 *     created_at, last_used_at, last_used_ip)
 *   - POST /settings/api-tokens — mint a new token (name + scope
 *     subset); response includes the plaintext token ONCE
 *   - DELETE /settings/api-tokens/{token} — revoke immediately
 *
 * Allowed scopes are intentionally a SUBSET of all Sanctum abilities
 * — only `landlord:manage` and `integration:webhook` are exposed
 * here. `tenant:read` is reserved for the mobile-app login flow and
 * cannot be minted from this UI.
 *
 * Audit trail: every issue + revoke writes a SecurityLog row so a
 * landlord investigating "who's calling our API" has a paper trail.
 */
class ApiTokenController extends Controller
{
    private const ALLOWED_SCOPES = ['landlord:manage', 'integration:webhook'];

    public function index(Request $request): Response
    {
        $user = $request->user();

        $tokens = $user->tokens()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'abilities', 'last_used_at', 'last_used_ip', 'expires_at', 'created_at'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'scopes' => is_string($t->abilities) ? json_decode($t->abilities, true) : ($t->abilities ?? []),
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'last_used_ip' => $t->last_used_ip,
                'expires_at' => $t->expires_at?->toIso8601String(),
                'created_at' => $t->created_at?->toIso8601String(),
            ]);

        return Inertia::render('ApiTokens/Index', [
            'tokens' => $tokens,
            'allowedScopes' => self::ALLOWED_SCOPES,
            'plaintextToken' => $request->session()->pull('plaintextToken'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:50'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['required', 'string', Rule::in(self::ALLOWED_SCOPES)],
        ]);

        $user = $request->user();
        $newToken = $user->createToken(
            $validated['name'],
            $validated['scopes'],
            now()->addYear(),
        );

        SecurityLog::create([
            'user_id' => $user->id,
            'event_type' => 'api_token_issued',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'token_id' => $newToken->accessToken->id,
                'name' => $validated['name'],
                'scopes' => $validated['scopes'],
            ],
        ]);

        return back()->with('plaintextToken', $newToken->plainTextToken);
    }

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        $user = $request->user();
        $token = $user->tokens()->where('id', $tokenId)->first();

        if (! $token) {
            return back()->withErrors(['token' => 'Token not found.']);
        }

        $name = $token->name;
        $scopes = is_string($token->abilities) ? json_decode($token->abilities, true) : ($token->abilities ?? []);

        $token->delete();

        SecurityLog::create([
            'user_id' => $user->id,
            'event_type' => 'api_token_revoked',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'token_id' => $tokenId,
                'name' => $name,
                'scopes' => $scopes,
            ],
        ]);

        return back()->with('success', 'Token revoked.');
    }
}
