<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Http\Requests\Finance\StoreOwnerPayoutRequest;
use App\Http\Traits\WithLandlordScope;
use App\Mail\OwnerPayoutMail;
use App\Models\Notification;
use App\Models\OwnerPayout;
use App\Models\PaymentConfiguration;
use App\Models\PropertyOwner;
use App\Services\NotificationService;
use App\Services\OwnerLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

/**
 * Phase-103 OWNER-PAYOUTS: the landlord/PM records what they have actually remitted to an
 * owner, and can void a mistaken record. Phase-104 adds a remittance advice (PDF email +
 * in-app notification) on record + resend. Every query is EXPLICITLY landlord-scoped
 * (getLandlordId) with same-tenant abort_unless guards — the Finance-module convention,
 * defense-in-depth over TenantScope's boot-conditional global scope.
 */
class OwnerPayoutController extends Controller
{
    use WithLandlordScope;

    public function store(StoreOwnerPayoutRequest $request, PropertyOwner $owner, OwnerLedgerService $ledger, NotificationService $notifications): RedirectResponse
    {
        $landlordId = $this->getLandlordId();
        abort_unless((int) $owner->landlord_id === $landlordId, 404);

        $validated = $request->validated();
        // Record the payout in the landlord's configured currency (the owner-money domain
        // is single-currency per landlord, like the statement it settles against).
        $currency = PaymentConfiguration::where('landlord_id', $landlordId)->first()?->default_currency
            ?? Currency::default();

        $payout = OwnerPayout::create([
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

        $this->sendRemittance($payout, $owner, $currency, $ledger, $notifications);

        return back()->with('success', __('owners.messages.payout_recorded'));
    }

    /** Re-send the remittance advice for an existing (non-voided) payout. */
    public function advice(PropertyOwner $owner, OwnerPayout $payout, OwnerLedgerService $ledger, NotificationService $notifications): RedirectResponse
    {
        $this->authorize('view', $payout);

        $landlordId = $this->getLandlordId();
        abort_unless((int) $owner->landlord_id === $landlordId, 404);
        abort_unless((int) $payout->landlord_id === $landlordId && (int) $payout->property_owner_id === $owner->id, 404);

        if ($payout->voided_at !== null) {
            return back()->with('error', __('owners.messages.payout_voided_no_advice'));
        }
        if (blank($owner->email)) {
            return back()->with('error', __('owners.messages.payout_no_email'));
        }

        $currency = PaymentConfiguration::where('landlord_id', $landlordId)->first()?->default_currency
            ?? Currency::default();

        $this->sendRemittance($payout, $owner, $currency, $ledger, $notifications);

        return back()->with('success', __('owners.messages.payout_advice_sent', ['email' => $owner->email]));
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

    /**
     * Send the remittance advice: a PDF email to the owner (if they have an email) + an
     * in-app notification (if they have a portal login). Resilient — a login-less / email-
     * less owner simply gets whichever channel applies (or none); recording never depends
     * on it.
     */
    private function sendRemittance(OwnerPayout $payout, PropertyOwner $owner, Currency $currency, OwnerLedgerService $ledger, NotificationService $notifications): void
    {
        // The payout is ALREADY recorded by the time we get here — recording is the source of
        // truth and must never fail because a downstream dispatch (ledger aggregate, mail
        // broker, a dangling owner login) throws. Isolate + log; never bubble a 500 that would
        // make the manager re-record a duplicate.
        try {
            $this->dispatchRemittance($payout, $owner, $currency, $ledger, $notifications);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Owner payout remittance dispatch failed', [
                'payout_id' => $payout->id,
                'owner_id' => $owner->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dispatchRemittance(OwnerPayout $payout, PropertyOwner $owner, Currency $currency, OwnerLedgerService $ledger, NotificationService $notifications): void
    {
        $summary = $ledger->summary((int) $owner->landlord_id, $owner->id);
        $payoutData = [
            'id' => $payout->id,
            'amount' => (float) $payout->amount,
            'currency_code' => $currency->value,
            'paid_on' => $payout->paid_on?->format('Y-m-d'),
            'method' => $payout->method,
            'reference' => $payout->reference,
            'notes' => $payout->notes,
        ];

        if (filled($owner->email)) {
            Mail::to($owner->email)->queue(new OwnerPayoutMail(
                $payoutData,
                $summary,
                $currency->symbol(),
                $owner->name,
                $owner->landlord?->name ?? config('app.name'),
            ));
        }

        if ($owner->user_id !== null) {
            $notifications->notifyInApp(
                recipientId: (int) $owner->user_id,
                type: Notification::TYPE_OWNER_PAYOUT_SENT,
                subject: __('owners.payouts.notify_subject'),
                message: __('owners.payouts.notify_body', [
                    'amount' => $currency->symbol().' '.number_format((float) $payout->amount, 2),
                ]),
                data: ['payout_id' => $payout->id],
                landlordId: (int) $owner->landlord_id,
            );
        }
    }
}
