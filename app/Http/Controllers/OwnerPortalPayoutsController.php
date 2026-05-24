<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Http\Traits\ResolvesCurrentOwner;
use App\Models\OwnerPayout;
use App\Models\PaymentConfiguration;
use App\Services\OwnerLedgerService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-103 OWNER-PAYOUTS: the owner's READ-ONLY view of what the manager has remitted to
 * them + their running balance. Scope comes only from ResolvesCurrentOwner (the authed
 * owner's user_id + landlord_id) — never a request param; an owner can only ever see their
 * own payouts.
 */
class OwnerPortalPayoutsController extends Controller
{
    use ResolvesCurrentOwner;

    public function index(OwnerLedgerService $ledger): Response
    {
        $owner = $this->currentOwner();
        $landlordId = (int) $owner->landlord_id;

        $currency = PaymentConfiguration::where('landlord_id', $landlordId)->first()?->default_currency
            ?? Currency::default();

        $payouts = OwnerPayout::query()
            ->where('landlord_id', $landlordId)
            ->where('property_owner_id', $owner->id)
            ->whereNull('voided_at')
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (OwnerPayout $p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'paid_on' => $p->paid_on?->format('Y-m-d'),
                'method' => $p->method,
                'reference' => $p->reference,
            ]);

        return Inertia::render('Owner/Payouts', [
            'summary' => $ledger->summary($landlordId, $owner->id),
            'payouts' => $payouts->values(),
            'currencySymbol' => $currency->symbol(),
        ]);
    }
}
