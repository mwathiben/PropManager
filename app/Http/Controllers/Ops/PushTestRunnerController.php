<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Phase-39 PUSH-EXTEND-3: super_admin manual push test runner at
 * /ops/push. Replaces tinker-script-based testing for incident
 * debugging — fills in for cases where the SW push handler is
 * suspected of misbehaving (deep-link routing, VAPID expiry, etc.)
 * and the operator wants end-to-end validation in <30 seconds.
 */
class PushTestRunnerController extends Controller
{
    public function show(): InertiaResponse
    {
        $users = User::query()
            ->whereIn('role', ['landlord', 'tenant', 'caretaker'])
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'email', 'role']);

        return Inertia::render('Ops/PushTester', [
            'users' => $users,
        ]);
    }

    public function send(Request $request, PushNotificationService $push): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'title' => 'required|string|max:120',
            'body' => 'required|string|max:500',
            'click_url' => 'nullable|string|max:255',
        ]);

        $delivered = $push->send(
            userId: (int) $validated['user_id'],
            title: $validated['title'],
            body: $validated['body'],
            data: null,
            landlordId: null,
            clickUrl: $validated['click_url'] ?? null,
        );

        return back()->with(
            $delivered ? 'success' : 'error',
            $delivered
                ? "Push delivered to user {$validated['user_id']}."
                : "Push delivery failed for user {$validated['user_id']} — check VAPID keys + active subscriptions.",
        );
    }
}
