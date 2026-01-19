<?php

namespace App\Http\Requests\MoveOut;

use Illuminate\Foundation\Http\FormRequest;

class CompleteMoveOutSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settlement_method' => 'required|in:cash,bank_transfer,mobile_money,offset',
            'settlement_reference' => 'nullable|string|max:255',
        ];
    }
}
