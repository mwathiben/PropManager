<?php

namespace App\Broadcasting;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class LandlordChannel
{
    public function join(User $user, int $landlordId): bool
    {
        Log::channel('single')->debug('Broadcasting: landlord channel subscription attempt', [
            'channel' => "landlord.{$landlordId}",
            'user_id' => $user->id,
            'user_role' => $user->role,
        ]);

        if ($user->role === 'super_admin') {
            return true;
        }

        if ($user->role === 'landlord' && $user->id === $landlordId) {
            return true;
        }

        if ($user->role === 'caretaker' && $user->landlord_id === $landlordId) {
            return true;
        }

        return false;
    }
}
