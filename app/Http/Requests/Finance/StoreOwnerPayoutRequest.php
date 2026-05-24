<?php

namespace App\Http\Requests\Finance;

use App\Models\OwnerPayout;
use Illuminate\Foundation\Http\FormRequest;

class StoreOwnerPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', OwnerPayout::class);
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01|max:9999999999',
            'paid_on' => 'required|date',
            'method' => 'required|in:bank_transfer,mpesa,cheque,cash,other',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
