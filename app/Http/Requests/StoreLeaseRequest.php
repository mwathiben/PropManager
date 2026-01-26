<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isLandlord() || auth()->user()->isCaretaker();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20',
            'id_number' => 'nullable|string',
            'rent_amount' => 'required|numeric|min:0',
            'service_charge' => 'nullable|numeric|min:0',
            'deposit_amount' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'lease_doc' => 'nullable|file|mimes:pdf,jpg,png|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => __('validation.custom.email.unique'),
            'rent_amount.min' => __('validation.custom.rent_amount.min'),
            'deposit_amount.min' => __('validation.custom.deposit_amount.min'),
        ];
    }
}
