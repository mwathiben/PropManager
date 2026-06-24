<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isScopeOwner() || auth()->user()->isCaretaker();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'tax_id' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
            // Phase-75 VENDOR-ROUTING-1: trade competencies (allow-list gated
            // in Vendor::syncSpecialties).
            'specialties' => 'sometimes|array',
            'specialties.*' => 'string|max:64',
        ];
    }
}
