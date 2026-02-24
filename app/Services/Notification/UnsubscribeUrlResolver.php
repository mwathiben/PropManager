<?php

namespace App\Services\Notification;

use App\Models\User;
use Illuminate\Support\Facades\URL;

class UnsubscribeUrlResolver
{
    public function resolve(User $recipient): ?string
    {
        if ($recipient->isTenant()) {
            return URL::temporarySignedRoute(
                'email.preferences',
                now()->addDays(30),
                ['user' => $recipient->id]
            );
        }

        if ($recipient->isLandlord() || $recipient->isCaretaker()) {
            return route('notifications.settings');
        }

        return null;
    }
}
