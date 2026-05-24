<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Http\Requests\Finance\StoreOwnerPayoutRequest;
use App\Http\Traits\WithLandlordScope;
use App\Models\OwnerPayout;
use App\Models\PaymentConfiguration;
use App\Models\PropertyOwner;
use Illuminate\Http\RedirectResponse;

/**
 * Phase-103 OWNER-PAYOUTS: the landlord/PM records what they have actually remitted to an
 * owner, and can void a mistaken record. Every query is EXPLICITLY landlord-scoped
 * (getLandlordId) with same-tenant abort_unless guards — the Finance-module convention,
 * defense-in-depth over TenantScope's boot-conditional global scope.
 */
class OwnerPayoutController extends Controller
{
    use WithLandlordScope;

    public function store(StoreOwnerPayoutRequest $request, PropertyOwner $owner): RedirectResponse
    {
        $landlordId = $this->getLandlordId();
        abort_unless((int) $owner->landlord_id === $landlordId, 404);

        $validated = $request->validated();
        // Record the payout in the landlord's configured currency (the owner-money domain
        // is single-currency per landlord, like the statement it settles against).
        $currency = PaymentConfiguration::where('landlord_id', $landlordId)->first()?->default_currency
            ?? Currency::default();

        OwnerPayout::create([
            'landlord_id' => $landlordId,
            'property_owner_id' => $owner->id,
            'amount' => $validated['amount'],
            'currency' => $currency->value,
            'paid_on' => $validated['paid_on'],
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', __('owners.messages.payout_recorded'));
    }

    public function void(PropertyOwner $owner, OwnerPayout $payout): RedirectResponse
    {
        $this->authorize('void', $payout);

        $landlordId = $this->getLandlordId();
        abort_unless((int) $owner->landlord_id === $landlordId, 404);
        abort_unless((int) $payout->landlord_id === $landlordId && (int) $payout->property_owner_id === $owner->id, 404);

        if ($payout->voided_at !== null) {
            return back()->with('info', __('owners.messages.payout_already_voided'));
        }

        $payout->forceFill(['voided_at' => now()])->save();

        return back()->with('success', __('owners.messages.payout_voided'));
    }
}
