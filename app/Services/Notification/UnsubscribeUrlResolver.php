<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\User;
use Illuminate\Support\Facades\URL;

class UnsubscribeUrlResolver
{
    public function resolve(User $recipient): ?string
    {
        return $this->generateUrl($recipient, 'email.preferences');
    }

    public function resolveForHeader(User $recipient): ?string
    {
        return $this->generateUrl($recipient, 'email.unsubscribe');
    }

    private function generateUrl(User $recipient, string $signedRoute): ?string
    {
        if ($recipient->isTenant()) {
            return URL::temporarySignedRoute(
                $signedRoute,
                now()->addDays(30),
                ['user' => $recipient->id]
            );
        }

        if ($recipient->isScopeOwner() || $recipient->isCaretaker()) {
            return route('notifications.settings');
        }

        return null;
    }
}
