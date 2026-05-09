<?php

namespace App\Http\Requests\Lease;

use Illuminate\Foundation\Http\FormRequest;

class WalletAdjustmentRequest extends FormRequest
{
    /**
     * PRIV-1: route is only gated by 'auth' middleware. Without this
     * authorize() check a tenant under landlord X could route-bind their
     * own lease and credit their wallet_balance with arbitrary amounts.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $lease = $this->route('lease');

        if (! $user || ! $lease) {
            return false;
        }

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            return false;
        }

        $landlordId = $user->isCaretaker() ? (int) $user->landlord_id : (int) $user->id;

        return (int) $lease->landlord_id === $landlordId;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:credit,debit',
            // VALID-8 echo: decimal:0,2 + max guards against scientific
            // notation and DECIMAL(12,2) overflow on wallet_balance.
            'amount' => ['required', 'decimal:0,2', 'min:0.01', 'max:9999999.99'],
            'reason' => 'required|string|max:255',
        ];
    }
}
