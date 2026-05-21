<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LandlordWalletSetting;
use App\Services\Wallet\WalletAutoApplyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-76 WALLET-DEEP AUTO-APPLY-3: per-landlord wallet auto-apply mode. A
 * landlord only ever reads/writes their own row (keyed on the authed user);
 * the route is landlord-only.
 */
class WalletSettingsController extends Controller
{
    public function __construct(private readonly WalletAutoApplyResolver $resolver) {}

    public function show(Request $request): Response
    {
        return Inertia::render('Wallet/Settings', [
            'mode' => $this->resolver->mode((int) $request->user()->id),
            'modes' => (array) config('wallet.auto_apply_modes', []),
            'default' => (string) config('wallet.default_auto_apply_mode'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'auto_apply_mode' => ['required', Rule::in((array) config('wallet.auto_apply_modes', []))],
        ]);

        $landlordId = (int) $request->user()->id;

        LandlordWalletSetting::updateOrCreate(
            ['landlord_id' => $landlordId],
            ['auto_apply_mode' => $data['auto_apply_mode']],
        );

        $this->resolver->flush($landlordId);

        return redirect()->route('wallet.settings')->with('success', __('wallet.settings.saved'));
    }
}
