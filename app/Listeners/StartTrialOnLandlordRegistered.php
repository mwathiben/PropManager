<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\Subscriptions\TrialStartService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Phase-60 TRIAL-DEPTH-1: auto-discovered listener that mints a
 * trial subscription when a landlord signs up. Fail-soft via
 * Log::error so a trial-creation hiccup never prevents signup.
 */
class StartTrialOnLandlordRegistered
{
    public function __construct(
        private readonly TrialStartService $service,
    ) {}

    public function handle(Registered $event): void
    {
        $user = $event->user;

        try {
            $this->service->startTrialFor($user);
        } catch (Throwable $e) {
            Log::error('trial_start_listener_failed', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
