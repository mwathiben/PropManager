<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Phase-40 GATEWAY-PREF-2: super_admin per-landlord gateway preference
 * switcher. Mirrors the landlord_id-scoped admin tooling pattern.
 */
class AdminGatewaysController extends Controller
{
    public function index(): InertiaResponse
    {
        $landlords = User::query()
            ->where('role', 'landlord')
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'email', 'payment_gateway_preference']);

        $configs = PaymentConfiguration::query()
            ->whereIn('landlord_id', $landlords->pluck('id'))
            ->get()
            ->keyBy('landlord_id');

        $rows = $landlords->map(function (User $u) use ($configs) {
            $config = $configs->get($u->id);

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'preference' => $u->payment_gateway_preference ?? 'auto',
                'paystack_enabled' => (bool) ($config?->hasPaystackConfig() ?? false),
                'stripe_enabled' => (bool) ($config?->hasStripeConfig() ?? false),
            ];
        });

        return Inertia::render('Admin/Gateways/Index', [
            'rows' => $rows,
        ]);
    }

    public function update(Request $request, User $user, PaymentGatewayManager $manager): RedirectResponse
    {
        $validated = $request->validate([
            'preference' => 'required|string|in:paystack,stripe,auto',
        ]);

        $pref = $validated['preference'];
        if ($pref !== 'auto' && ! $manager->supports($pref)) {
            return back()->with('error', "Unknown gateway: {$pref}");
        }

        $user->payment_gateway_preference = $pref;
        $user->save();

        return back()->with('success', "Updated {$user->name}'s gateway preference to {$pref}.");
    }
}
