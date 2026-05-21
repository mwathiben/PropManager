<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Legal\TenantSubjectResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase-72 SUBJECT-PICKER: suggests a tenant's holdable records for the wizard.
 * Landlord-only; the tenant must belong to the acting landlord (bypass scope on
 * lookup so a foreign tenant_id fails the ownership check, never silently 404s
 * a real tenant the landlord legitimately owns).
 */
class LegalHoldSubjectController extends Controller
{
    public function suggest(Request $request, TenantSubjectResolver $resolver): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        abort_unless($user->isLandlord(), 403);

        $tenant = User::query()->withoutGlobalScopes()->find($data['tenant_id']);
        abort_unless(
            $tenant !== null && (int) $tenant->landlord_id === (int) $user->id,
            403,
        );

        return response()->json([
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name],
            'groups' => $resolver->suggest($tenant, (int) $user->id),
        ]);
    }
}
