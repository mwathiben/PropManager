<?php

declare(strict_types=1);

namespace App\Http\Requests\Water;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase-97 WATER-CLIENT-BILLING: a landlord records a payment a water client made
 * (cash / M-Pesa received), applied across the connection's unpaid charges.
 */
class RecordWaterClientPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isLandlord() ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
        ];
    }
}
