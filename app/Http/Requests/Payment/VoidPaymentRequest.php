<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class VoidPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        // Deny unauthenticated users
        if (! $user) {
            return false;
        }

        // Only landlords can void payments (sensitive financial operation)
        return $user->isLandlord();
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }
}
