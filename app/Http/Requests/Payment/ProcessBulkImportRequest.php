<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class ProcessBulkImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isLandlord() || auth()->user()->isCaretaker();
    }

    public function rules(): array
    {
        $mode = $this->input('mode', 'current');

        $baseRules = [
            'mode' => 'sometimes|in:current,historical',
            'payments' => 'required|array|min:1',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.payment_date' => 'required|date',
            'payments.*.payment_method' => 'required|string',
        ];

        if ($mode === 'historical') {
            return array_merge($baseRules, [
                'payments.*.unit_id' => 'required|integer',
                'payments.*.tenant_name' => 'required|string',
                'building_id' => 'required|integer',
            ]);
        }

        return array_merge($baseRules, [
            'payments.*.tenant_id' => 'required|integer',
            'payments.*.allocations' => 'present|array',
        ]);
    }
}
