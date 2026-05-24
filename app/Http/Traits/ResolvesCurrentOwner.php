<?php

declare(strict_types=1);

namespace App\Http\Traits;

use App\Models\PropertyOwner;

/**
 * Phase-102 OWNER-PORTAL: the single source of an owner-portal request's scope — the
 * authed owner's PropertyOwner, bound to both user_id AND their PM's landlord_id.
 * Every owner-portal query MUST derive its scope from this (never from a route param).
 */
trait ResolvesCurrentOwner
{
    protected function currentOwner(): PropertyOwner
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->isOwner(), 403);

        return PropertyOwner::where('user_id', $user->id)
            ->where('landlord_id', $user->landlord_id)
            ->firstOrFail();
    }
}
