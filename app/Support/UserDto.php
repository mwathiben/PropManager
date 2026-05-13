<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Phase-20 AUTHZ-FRONT-6 (closes Phase-15 FRONT-8 deferral): slim DTO
 * shipped to the Inertia frontend instead of the full Eloquent User
 * model. Pre-Phase-20, HandleInertiaRequests shared $user directly —
 * exposing model timestamps, internal flags, and any future-added
 * columns. The slim DTO is explicit + auditable: every field listed
 * here is intentional.
 *
 * Stay in sync with resources/js/composables/useAuth.ts User type.
 * Adding a field here means adding it to the TypeScript interface
 * too — Phase20AuthzFrontTest::test_inertia_user_shape_is_slim_dto
 * pins the exact key set.
 */
class UserDto
{
    /**
     * @return array<string, mixed>
     */
    public static function from(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'landlord_id' => $user->landlord_id,
            'profile_photo_url' => $user->profile_photo_url ?? null,
            'is_restricted' => $user->isRestricted(),
            'abilities' => AuthAbilities::for($user),
        ];
    }
}
