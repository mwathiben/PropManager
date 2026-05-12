# Inertia share() Audit

Phase-15 FRONT-8: every Inertia request ships the `share()` payload to the client. Fields that ride along on every page are visible to every authenticated user — including fields the page never renders. This audit catalogues what's shared and recommends where to apply Inertia's `defer` / lazy props pattern.

## Current share() payload (HandleInertiaRequests::share)

| Field | Shape | Risk | Recommended |
|-------|-------|------|-------------|
| `auth.user` | Full User model | **HIGH** — ships every column including `last_login_at`, `mobile_number`, `national_id` (encrypted but still shipped as ciphertext), `bank_details` (same), `archived_at`, `kyc_completed_at`, `restricted_at`, `restriction_reason` | Slim DTO with only what the layout needs: id, name, email, role, profile_photo_path |
| `impersonating` | bool | LOW | OK |
| `impersonating_name` | string | LOW | OK |
| `currency` | callable | LOW | OK (lazy via closure already) |
| `navBadges` | callable | MED | Defer — only the layout reads it |
| `featureAccess` | array | MED | Defer — only feature-gated pages read it |
| `pendingInvitations` | Inertia::defer | LOW | Already deferred |

## The auth.user concern

`auth.user` ships the full User model. Hidden columns (`password`, `remember_token`, `two_factor_secret`) are stripped by `$hidden`, but model accessors and relationships can still leak via `toArray()`. Today auth.user serialisation includes ENCRYPTED ciphertext for `national_id` and `bank_details` — the plaintext is unreachable but the existence + length is visible.

### Mitigation pattern

```php
public function share(Request $request): array
{
    $user = $request->user();

    return [
        ...parent::share($request),
        'auth' => [
            'user' => $user ? $this->slimUser($user) : null,
        ],
        // ...
    ];
}

private function slimUser($user): array
{
    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'role' => $user->role,
        'profile_photo_path' => $user->profile_photo_path,
        // Add fields here only when a layout-level component needs them.
    ];
}
```

A Vue page that needs more (e.g. ProfileEdit needing mobile_number) loads its own copy via the controller — passing only what that page actually renders.

## Recommended next steps (not in Phase-15 scope)

1. Build the `slimUser` DTO + replace `'user' => $user` with the slim version.
2. Audit each Vue page reading `auth.user.X` — if X is not in the slim DTO, the page either gets X via its own props (controller-supplied) or X moves into the slim DTO.
3. Defer `navBadges` + `featureAccess` to only the pages that need them.

This is mechanical but extensive — touches every layout + every Vue page that references `auth.user.*`. Tracked as Phase-15 follow-up.

## Cross-references

- Phase-13 DPA-6: SensitiveDataMaskingProcessor — masks PII at log capture
- Phase-13 BREACH-4 / DPA-4: User restriction fields are part of the share() payload today
- Phase-15 FRONT-7: Vue-side validation pinning — same broader theme of frontend-side trust
