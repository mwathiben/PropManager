<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class RefundDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isScopeOwner() || auth()->user()->isCaretaker();
    }

    public function rules(): array
    {
        $lease = $this->route('lease');
        $maxAmount = $lease?->deposit_amount ?? 0;

        return [
            'refund_amount' => "required|numeric|min:0|max:{$maxAmount}",
            'deductions' => "nullable|numeric|min:0|max:{$maxAmount}",
            'deduction_reason' => 'nullable|string|max:500',
            'payment_method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
