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
                // Phase-42 TAX-2: VAT registration state surfaces here so
                // operators can spot landlords issuing KES invoices
                // without a KRA PIN on file.
                'vat_registered' => (bool) ($config?->isVatRegistered() ?? false),
                'stripe_tax_enabled' => (bool) ($config?->hasStripeTaxEnabled() ?? false),
                'vat_rate_bps_override' => $config?->vat_rate_bps_override,
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

    /**
     * Phase-42 TAX-2: super_admin updates a landlord's KRA VAT
     * registration + Stripe Tax opt-in. kra_pin must match the
     * Kenya VAT PIN format (`A` or `P` + 9 digits + capital letter);
     * vat_rate_bps_override defaults to NULL = use the 16% statutory
     * rate. stripe_tax_enabled controls automatic_tax on Stripe
     * PaymentIntents for non-KES charges.
     */
    public function updateTaxConfig(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'kra_pin' => ['nullable', 'string', 'regex:/^[AP]\d{9}[A-Z]$/'],
            'vat_rate_bps_override' => ['nullable', 'integer', 'between:0,10000'],
            'stripe_tax_enabled' => ['sometimes', 'boolean'],
        ]);

        // TenantScope's creating callback overwrites landlord_id with
        // the auth user's landlord_id, which is null for super_admin —
        // suppressing model events here keeps the explicit landlord_id
        // from getOrCreateForLandlord intact.
        $config = PaymentConfiguration::withoutEvents(
            fn () => PaymentConfiguration::getOrCreateForLandlord($user->id),
        );
        $config->kra_pin = $validated['kra_pin'] ?? null;
        $config->vat_rate_bps_override = $validated['vat_rate_bps_override'] ?? null;
        $config->stripe_tax_enabled = (bool) ($validated['stripe_tax_enabled'] ?? false);
        $config->saveQuietly();

        return back()->with('success', __('payments.tax.updated_flash', ['name' => $user->name]));
    }
}
