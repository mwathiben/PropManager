<?php

namespace App\Broadcasting;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class TenantChannel
{
    public function join(User $user, int $tenantId): bool
    {
        Log::channel('single')->debug('Broadcasting: tenant channel subscription attempt', [
            'channel' => "tenant.{$tenantId}",
            'user_id' => $user->id,
            'user_role' => $user->role,
        ]);

        return $user->id === $tenantId && $user->role === 'tenant';
    }
}
