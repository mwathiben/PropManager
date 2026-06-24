<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidateBulkImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isScopeOwner() || $user->isCaretaker());
    }

    public function rules(): array
    {
        $user = $this->user();
        $landlordId = $user->isScopeOwner() ? $user->id : $user->landlord_id;

        return [
            'file' => 'required|file|mimes:csv,txt|max:5120',
            'building_id' => [
                'required',
                'integer',
                Rule::exists('buildings', 'id')->where('landlord_id', $landlordId),
            ],
            'mode' => 'required|in:current,historical',
        ];
    }
}
