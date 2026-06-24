<?php

namespace App\Broadcasting;

use App\Models\Lease;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class LeaseChannel
{
    public function join(User $user, int $leaseId): bool
    {
        Log::channel('single')->debug('Broadcasting: lease channel subscription attempt', [
            'channel' => "lease.{$leaseId}",
            'user_id' => $user->id,
            'user_role' => $user->role,
        ]);

        $lease = Lease::find($leaseId);

        if (! $lease) {
            return false;
        }

        if ($user->role === 'super_admin') {
            return true;
        }

        if ($user->role === 'tenant' && $lease->tenant_id === $user->id) {
            return true;
        }

        if ($user->isScopeOwner() || $user->isCaretaker()) {
            $landlordId = $user->isScopeOwner() ? $user->id : $user->landlord_id;

            return $lease->landlord_id === $landlordId;
        }

        return false;
    }
}
