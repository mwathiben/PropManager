<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Phase-84 PAY-METHODS-2: per-type validation for a tenant's saved payment
 * method (mpesa | bank | card). The single-default-per-(user,type) invariant is
 * enforced by TenantPaymentMethodService.
 */
class StoreTenantPaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTenant() ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['mpesa', 'bank', 'card'])],
            'is_default' => ['nullable', 'boolean'],

            // mpesa
            'phone' => ['required_if:type,mpesa', 'nullable', 'string', 'regex:/^\+?[1-9]\d{6,14}$/'],

            // bank
            'bank_name' => ['required_if:type,bank', 'nullable', 'string', 'max:100'],
            'account_name' => ['required_if:type,bank', 'nullable', 'string', 'max:100'],
            'account_number' => ['required_if:type,bank', 'nullable', 'string', 'max:34'],

            // card (metadata only — no PAN; tokenisation handled by the gateway)
            'brand' => ['required_if:type,card', 'nullable', 'string', 'max:30'],
            'last4' => ['required_if:type,card', 'nullable', 'digits:4'],
        ];
    }
}
